<?php

namespace App\Modules\Affiliates\Controllers;

// DOCUMENTO_RECTOR §4 Grupo B; RF-005, RF-006, RF-021–RF-023

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\AffiliateNote;
use App\Modules\Affiliates\Models\Person;
use App\Modules\PILALiquidation\Enums\PilaLiquidationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AffiliateController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Affiliate::class, 'affiliate', [
            'except' => ['export'],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));

        $paginator = $this->affiliateListingQuery($request)
            ->with($this->listingWith())
            ->paginate($perPage);

        $ids = collect($paginator->items())->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $lastPaid = $this->loadLastPaidYyyymmByAffiliateIds($ids);
        $noteCounts = $this->loadNotesCountByAffiliateIds($ids);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Affiliate $a): array => $this->affiliateToListArray(
                $a,
                $lastPaid[$a->id] ?? null,
                $noteCounts[$a->id] ?? 0,
            ))->values()->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * RF-023 — exportación CSV o Excel (mismos filtros que index).
     */
    public function export(Request $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewAny', Affiliate::class);

        $format = strtolower($request->string('format', 'csv')->toString());
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            return response()->json(['message' => 'Formato no soportado. Use format=csv o format=xlsx.'], 400);
        }

        $maxRows = min(5000, max(1, (int) $request->input('max_rows', 5000)));
        $rows = $this->affiliateListingQuery($request)
            ->with($this->listingWith())
            ->limit($maxRows)
            ->get();

        $ids = $rows->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $lastPaid = $this->loadLastPaidYyyymmByAffiliateIds($ids);
        $noteCounts = $this->loadNotesCountByAffiliateIds($ids);

        $filename = 'export-afiliados-'.now()->format('Y-m-d_His').($format === 'xlsx' ? '.xlsx' : '.csv');

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($rows, $lastPaid, $noteCounts): void {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $this->exportHeaders(), ';');
                foreach ($rows as $a) {
                    fputcsv($out, $this->exportRowValues($a, $lastPaid[$a->id] ?? null, $noteCounts[$a->id] ?? 0), ';');
                }
                fclose($out);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        return response()->streamDownload(function () use ($rows, $lastPaid, $noteCounts): void {
            $tmp = tempnam(sys_get_temp_dir(), 'aff_xlsx_');
            if ($tmp === false) {
                throw new \RuntimeException('No se pudo crear archivo temporal.');
            }
            try {
                $writer = new Writer;
                $writer->openToFile($tmp);
                $writer->addRow(Row::fromValues($this->exportHeaders()));
                foreach ($rows as $a) {
                    $writer->addRow(Row::fromValues($this->exportRowValues($a, $lastPaid[$a->id] ?? null, $noteCounts[$a->id] ?? 0)));
                }
                $writer->close();
                readfile($tmp);
            } finally {
                if (is_file($tmp)) {
                    unlink($tmp);
                }
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return list<string>
     */
    private function exportHeaders(): array
    {
        return [
            'id',
            'nombre_completo',
            'documento',
            'tipo_cotizante',
            'tipo_cliente',
            'estado_codigo',
            'estado_nombre',
            'mora',
            'indicador_pagos',
            'eps',
            'afp',
            'arl',
            'ccf',
            'operador_pila',
            'ultimo_periodo_pagado',
            'notas_operativas',
            'notas_formales_count',
            'pagador_nit',
            'pagador_razon_social',
            'asesor_id',
            'creado',
        ];
    }

    /**
     * @return list<int|string|null>
     */
    private function exportRowValues(Affiliate $a, ?int $lastYyyymm, int $notesFormalCount): array
    {
        $list = $this->affiliateToListArray($a, $lastYyyymm, $notesFormalCount);

        return [
            $list['id'],
            $list['fullName'],
            $list['documentNumber'],
            $list['contributorTypeCode'],
            $list['clientType'],
            $list['statusCode'],
            $list['statusName'],
            $list['moraStatus'],
            $list['paymentIndicator'],
            $list['epsName'],
            $list['afpName'],
            $list['arlName'],
            $list['ccfName'],
            $list['pilaOperatorCode'],
            $list['lastPaidPeriod'],
            $list['operationalNotes'] !== null ? mb_substr((string) $list['operationalNotes'], 0, 500) : '',
            $list['formalNotesCount'],
            $list['payerNit'],
            $list['payerRazonSocial'],
            $list['advisorId'],
            $list['createdAt'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function affiliateToListArray(Affiliate $a, ?int $lastYyyymm, int $notesFormalCount): array
    {
        $p = $a->person;
        $ssp = $a->currentSocialSecurityProfile;
        $ap = $a->currentAffiliatePayer;
        $pay = $ap?->payer;

        $fullName = trim(implode(' ', array_filter([
            $p?->first_name,
            $p?->second_name,
            $p?->first_surname,
            $p?->second_surname,
        ], static fn (?string $x): bool => $x !== null && $x !== '')));

        $period = $this->decodeYyyymm($lastYyyymm);
        $lastPaidStr = $period === null ? null : sprintf('%04d-%02d', $period['year'], $period['month']);

        return [
            'id' => $a->id,
            'documentNumber' => $p?->document_number,
            'fullName' => $fullName !== '' ? $fullName : null,
            'firstName' => $p?->first_name,
            'lastName' => $p?->first_surname,
            'clientType' => $a->client_type?->value,
            'statusId' => $a->status_id,
            'statusCode' => $a->status?->code,
            'statusName' => $a->status?->name,
            'moraStatus' => $a->mora_status,
            'paymentIndicator' => $this->paymentIndicatorLabel($a->mora_status),
            'contributorTypeCode' => $ap?->contributor_type_code,
            'epsName' => $ssp?->epsEntity?->name,
            'afpName' => $ssp?->afpEntity?->name,
            'arlName' => $ssp?->arlEntity?->name,
            'ccfName' => $ssp?->ccfEntity?->name,
            'pilaOperatorCode' => $pay?->pila_operator_code,
            'payerId' => $ap?->payer_id,
            'payerNit' => $pay?->nit,
            'payerRazonSocial' => $pay?->razon_social,
            'advisorId' => $ap?->advisor_id,
            'lastPaidYear' => $period['year'] ?? null,
            'lastPaidMonth' => $period['month'] ?? null,
            'lastPaidPeriod' => $lastPaidStr,
            'operationalNotes' => $a->operational_notes,
            'formalNotesCount' => $notesFormalCount,
            'createdAt' => $a->created_at?->toIso8601String(),
            'updatedAt' => $a->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return list<string>
     */
    private function listingWith(): array
    {
        return [
            'person',
            'status',
            'currentSocialSecurityProfile.epsEntity',
            'currentSocialSecurityProfile.afpEntity',
            'currentSocialSecurityProfile.arlEntity',
            'currentSocialSecurityProfile.ccfEntity',
            'currentAffiliatePayer.payer',
        ];
    }

    /**
     * @return Builder<Affiliate>
     */
    private function affiliateListingQuery(Request $request): Builder
    {
        $query = Affiliate::query()
            ->join('core_people', 'core_people.id', '=', 'afl_affiliates.person_id')
            ->orderBy('core_people.document_number')
            ->select('afl_affiliates.*');

        $this->applyListingFilters($request, $query);

        return $query;
    }

    /**
     * RF-022 — filtros Mis Afiliados.
     *
     * @param  Builder<Affiliate>  $query
     */
    private function applyListingFilters(Request $request, Builder $query): void
    {
        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim().'%';
            $query->where(function ($w) use ($term): void {
                $w->where('core_people.document_number', 'like', $term)
                    ->orWhere('core_people.first_name', 'like', $term)
                    ->orWhere('core_people.second_name', 'like', $term)
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

        if ($request->filled('contributor_type_code')) {
            $code = $request->string('contributor_type_code')->toString();
            $query->whereExists(function ($sub) use ($code): void {
                $sub->selectRaw('1')
                    ->from('afl_affiliate_payer as ap')
                    ->whereColumn('ap.affiliate_id', 'afl_affiliates.id')
                    ->whereNull('ap.end_date')
                    ->where('ap.contributor_type_code', $code);
            });
        }

        if ($request->filled('payer_id')) {
            $pid = (int) $request->input('payer_id');
            $query->whereExists(function ($sub) use ($pid): void {
                $sub->selectRaw('1')
                    ->from('afl_affiliate_payer as ap')
                    ->whereColumn('ap.affiliate_id', 'afl_affiliates.id')
                    ->whereNull('ap.end_date')
                    ->where('ap.payer_id', $pid);
            });
        }

        if ($request->filled('advisor_id')) {
            $aid = (int) $request->input('advisor_id');
            $query->whereExists(function ($sub) use ($aid): void {
                $sub->selectRaw('1')
                    ->from('afl_affiliate_payer as ap')
                    ->whereColumn('ap.affiliate_id', 'afl_affiliates.id')
                    ->whereNull('ap.end_date')
                    ->where('ap.advisor_id', $aid);
            });
        }

        if ($request->filled('pila_operator_code')) {
            $op = $request->string('pila_operator_code')->toString();
            $query->whereExists(function ($sub) use ($op): void {
                $sub->selectRaw('1')
                    ->from('afl_affiliate_payer as ap')
                    ->join('afl_payers as p', 'p.id', '=', 'ap.payer_id')
                    ->whereColumn('ap.affiliate_id', 'afl_affiliates.id')
                    ->whereNull('ap.end_date')
                    ->where('p.pila_operator_code', $op);
            });
        }

        foreach (['eps' => 'eps_entity_id', 'afp' => 'afp_entity_id', 'arl' => 'arl_entity_id', 'ccf' => 'ccf_entity_id'] as $param => $column) {
            if ($request->filled($param.'_entity_id')) {
                $eid = (int) $request->input($param.'_entity_id');
                $query->whereExists(function ($sub) use ($column, $eid): void {
                    $sub->selectRaw('1')
                        ->from('afl_social_security_profiles as ssp')
                        ->whereColumn('ssp.affiliate_id', 'afl_affiliates.id')
                        ->whereNull('ssp.valid_until')
                        ->where('ssp.'.$column, $eid);
                });
            }
        }

        if ($request->filled('payments_on_track')) {
            $v = strtolower($request->string('payments_on_track')->toString());
            if ($v === 'yes') {
                $query->where(function ($w): void {
                    $w->where('afl_affiliates.mora_status', 'like', '%AL_DIA%')
                        ->orWhere('afl_affiliates.mora_status', 'like', '%AL DIA%')
                        ->orWhere('afl_affiliates.mora_status', 'like', '%OK%')
                        ->orWhereNull('afl_affiliates.mora_status');
                });
            } elseif ($v === 'no') {
                $query->where(function ($w): void {
                    $w->where('afl_affiliates.mora_status', 'like', '%MORA%')
                        ->orWhere('afl_affiliates.mora_status', 'like', '%ATRAS%');
                });
            } elseif ($v === 'ahead') {
                $query->where('afl_affiliates.mora_status', 'like', '%ANTICIP%');
            }
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    private function loadLastPaidYyyymmByAffiliateIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('pila_liquidation_lines as pll')
            ->join('pila_liquidations as pl', 'pl.id', '=', 'pll.pila_liquidation_id')
            ->whereIn('pl.affiliate_id', $ids)
            ->where('pl.status', PilaLiquidationStatus::Confirmed->value)
            ->groupBy('pl.affiliate_id')
            ->selectRaw('pl.affiliate_id as affiliate_id, MAX(pll.period_year * 100 + pll.period_month) as yyyymm')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->affiliate_id] = (int) $row->yyyymm;
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    private function loadNotesCountByAffiliateIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = AffiliateNote::query()
            ->whereIn('affiliate_id', $ids)
            ->selectRaw('affiliate_id, COUNT(*) as c')
            ->groupBy('affiliate_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->affiliate_id] = (int) $row->c;
        }

        return $out;
    }

    private function paymentIndicatorLabel(?string $moraStatus): string
    {
        $m = $moraStatus === null ? '' : mb_strtoupper($moraStatus);
        if (str_contains($m, 'ANTICIP')) {
            return 'ANTICIPADO';
        }
        if (str_contains($m, 'MORA') || str_contains($m, 'ATRAS')) {
            return 'NO';
        }
        if (str_contains($m, 'AL_DIA') || str_contains($m, 'AL DIA') || $m === 'OK') {
            return 'SI';
        }

        return 'NEUTRO';
    }

    /**
     * @return array{year: int, month: int}|null
     */
    private function decodeYyyymm(?int $v): ?array
    {
        if ($v === null || $v <= 0) {
            return null;
        }

        $year = intdiv($v, 100);
        $month = $v % 100;

        return ['year' => $year, 'month' => $month];
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
}
