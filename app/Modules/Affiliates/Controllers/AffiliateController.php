<?php

namespace App\Modules\Affiliates\Controllers;

// DOCUMENTO_RECTOR §4 Grupo B; RF-005, RF-006, RF-021–RF-023

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AffiliateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));

        $paginator = $this->affiliateListingQuery($request)->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Affiliate $a): array => $this->affiliateToArray($a))->values()->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * RF-023 — exportación CSV (mismos filtros que index: q, client_type, status_id, mora_status).
     */
    public function export(Request $request): JsonResponse|StreamedResponse
    {
        $format = strtolower($request->string('format', 'csv')->toString());
        if ($format !== 'csv') {
            return response()->json(['message' => 'Formato no soportado. Use format=csv.'], 400);
        }

        $maxRows = min(5000, max(1, (int) $request->input('max_rows', 5000)));
        $rows = $this->affiliateListingQuery($request)->limit($maxRows)->get();

        $filename = 'export-afiliados-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'id',
                'documento',
                'primer_nombre',
                'primer_apellido',
                'tipo_cliente',
                'estado_id',
                'mora',
                'eps',
                'afp',
                'arl',
                'ccf',
                'notas_operativas',
                'creado',
            ], ';');
            foreach ($rows as $a) {
                $p = $a->person;
                $ssp = $a->currentSocialSecurityProfile;
                fputcsv($out, [
                    $a->id,
                    $p?->document_number,
                    $p?->first_name,
                    $p?->first_surname,
                    $a->client_type?->value,
                    $a->status_id,
                    $a->mora_status,
                    $ssp?->epsEntity?->name,
                    $ssp?->afpEntity?->name,
                    $ssp?->arlEntity?->name,
                    $ssp?->ccfEntity?->name,
                    $a->operational_notes !== null ? mb_substr((string) $a->operational_notes, 0, 200) : '',
                    $a->created_at?->toIso8601String(),
                ], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return Builder<Affiliate>
     */
    private function affiliateListingQuery(Request $request): Builder
    {
        $query = Affiliate::query()
            ->with([
                'person',
                'currentSocialSecurityProfile.epsEntity',
                'currentSocialSecurityProfile.afpEntity',
                'currentSocialSecurityProfile.arlEntity',
                'currentSocialSecurityProfile.ccfEntity',
            ])
            ->join('core_people', 'core_people.id', '=', 'afl_affiliates.person_id')
            ->orderBy('core_people.document_number')
            ->select('afl_affiliates.*');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim().'%';
            $query->where(function ($w) use ($term): void {
                $w->where('core_people.document_number', 'like', $term)
                    ->orWhere('core_people.first_name', 'like', $term)
                    ->orWhere('core_people.first_surname', 'like', $term)
                    ->orWhere('core_people.second_surname', 'like', $term);
            });
        }

        if ($request->filled('client_type')) {
            $query->where('afl_affiliates.client_type', $request->string('client_type')->toString());
        }

        if ($request->filled('status_id')) {
            $query->where('afl_affiliates.status_id', (int) $request->input('status_id'));
        }

        if ($request->filled('mora_status')) {
            $query->where('afl_affiliates.mora_status', $request->string('mora_status')->toString());
        }

        return $query;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_number' => ['required', 'string', 'max:32', 'unique:core_people,document_number'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'client_type' => ['nullable', 'string', Rule::enum(AffiliateClientType::class)],
        ]);

        $affiliate = DB::transaction(function () use ($validated): Affiliate {
            $person = Person::query()->create([
                'document_number' => $validated['document_number'],
                'first_name' => $validated['first_name'] ?? null,
                'first_surname' => $validated['last_name'] ?? null,
            ]);

            $clientType = isset($validated['client_type'])
                ? AffiliateClientType::from($validated['client_type'])
                : AffiliateClientType::SERVICONLI;

            return Affiliate::query()->create([
                'person_id' => $person->id,
                'client_type' => $clientType,
            ]);
        });

        $affiliate->load('person');

        return response()->json($this->affiliateToArray($affiliate), 201);
    }

    public function show(Affiliate $affiliate): JsonResponse
    {
        $affiliate->load('person');

        return response()->json($this->affiliateToArray($affiliate));
    }

    public function update(Request $request, Affiliate $affiliate): JsonResponse
    {
        $affiliate->load('person');

        $validated = $request->validate([
            'document_number' => [
                'sometimes',
                'required',
                'string',
                'max:32',
                Rule::unique('core_people', 'document_number')->ignore($affiliate->person_id),
            ],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'client_type' => ['nullable', 'string', Rule::enum(AffiliateClientType::class)],
        ]);

        DB::transaction(function () use ($affiliate, $validated): void {
            $personAttrs = [];
            if (array_key_exists('document_number', $validated)) {
                $personAttrs['document_number'] = $validated['document_number'];
            }
            if (array_key_exists('first_name', $validated)) {
                $personAttrs['first_name'] = $validated['first_name'];
            }
            if (array_key_exists('last_name', $validated)) {
                $personAttrs['first_surname'] = $validated['last_name'];
            }
            if ($personAttrs !== []) {
                $affiliate->person->update($personAttrs);
            }

            $affiliateAttrs = [];
            if (array_key_exists('client_type', $validated)) {
                $affiliateAttrs['client_type'] = AffiliateClientType::from($validated['client_type']);
            }
            if ($affiliateAttrs !== []) {
                $affiliate->update($affiliateAttrs);
            }
        });

        return response()->json($this->affiliateToArray($affiliate->fresh(['person'])));
    }

    public function destroy(Affiliate $affiliate): JsonResponse
    {
        DB::transaction(function () use ($affiliate): void {
            $person = $affiliate->person;
            $affiliate->delete();
            $person?->delete();
        });

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function affiliateToArray(Affiliate $a): array
    {
        $p = $a->person;
        $ssp = $a->currentSocialSecurityProfile;

        return [
            'id' => $a->id,
            'documentNumber' => $p?->document_number,
            'firstName' => $p?->first_name,
            'lastName' => $p?->first_surname,
            'clientType' => $a->client_type?->value,
            'statusId' => $a->status_id,
            'moraStatus' => $a->mora_status,
            'operationalNotes' => $a->operational_notes,
            'epsName' => $ssp?->epsEntity?->name,
            'afpName' => $ssp?->afpEntity?->name,
            'arlName' => $ssp?->arlEntity?->name,
            'ccfName' => $ssp?->ccfEntity?->name,
            'createdAt' => $a->created_at?->toIso8601String(),
            'updatedAt' => $a->updated_at?->toIso8601String(),
        ];
    }
}
