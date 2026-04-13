<?php

namespace App\Modules\Advisors\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advisors\Models\AdvisorCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdvisorCommissionController extends Controller
{
    /**
     * Listado paginado de comisiones — RF-100.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AdvisorCommission::class);

        $q = AdvisorCommission::query()->with(['advisor', 'affiliate.person']);

        if ($request->filled('advisor_id')) {
            $q->where('advisor_id', (int) $request->input('advisor_id'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->string('status')->toString());
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $paginator = $q->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (AdvisorCommission $c): array => $this->toListRow($c))->values()->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function update(Request $request, AdvisorCommission $advisorCommission): JsonResponse
    {
        $this->authorize('update', $advisorCommission);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['PAGADA', 'ANULADA'])],
        ]);

        if ($advisorCommission->status !== 'CALCULADA') {
            return response()->json([
                'message' => 'Solo se puede cambiar el estado desde CALCULADA.',
            ], 422);
        }

        $advisorCommission->update(['status' => $validated['status']]);

        return response()->json([
            'id' => $advisorCommission->id,
            'publicNumber' => $advisorCommission->public_number,
            'status' => $advisorCommission->status,
        ]);
    }

    /** @return array<string, mixed> */
    private function toListRow(AdvisorCommission $c): array
    {
        $adv = $c->advisor;
        $person = $c->affiliate?->person;
        $affiliateName = $person
            ? trim(($person->first_name ?? '').' '.($person->first_surname ?? '').' '.($person->second_surname ?? ''))
            : null;

        return [
            'id' => $c->id,
            'publicNumber' => $c->public_number,
            'advisorId' => $c->advisor_id,
            'advisorCode' => $adv?->code,
            'advisorName' => $adv ? trim($adv->first_name.' '.($adv->last_name ?? '')) : null,
            'affiliateId' => $c->affiliate_id,
            'affiliateName' => $affiliateName !== '' ? trim($affiliateName) : null,
            'commissionType' => $c->commission_type,
            'amountPesos' => (int) $c->amount_pesos,
            'status' => $c->status,
            'createdAt' => $c->created_at?->toIso8601String(),
        ];
    }
}
