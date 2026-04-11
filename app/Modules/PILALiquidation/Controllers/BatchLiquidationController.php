<?php

namespace App\Modules\PILALiquidation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Affiliations\Models\Payer;
use App\Modules\PILALiquidation\Models\LiquidationBatch;
use App\Modules\PILALiquidation\Models\LiquidationBatchLine;
use App\Modules\PILALiquidation\Services\BatchLiquidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * API para Flujo 4 — Liquidación por Lotes.
 *
 * @see DOCUMENTO_RECTOR §6 Flujo 4, RF-067..RF-070
 */
final class BatchLiquidationController extends Controller
{
    /**
     * Lista pagadores activos para el selector.
     */
    public function payers(): JsonResponse
    {
        $payers = Payer::query()
            ->where('status', 'ACTIVE')
            ->orderBy('razon_social')
            ->get(['id', 'nit', 'digito_verificacion', 'razon_social', 'pila_operator_code']);

        return response()->json([
            'data' => $payers->map(fn ($p) => [
                'id' => $p->id,
                'nit' => $p->nit,
                'dv' => $p->digito_verificacion,
                'razon_social' => $p->razon_social,
                'pila_operator_code' => $p->pila_operator_code,
            ])->values(),
        ]);
    }

    /**
     * Crea un lote borrador con todos los afiliados activos del pagador.
     */
    public function store(Request $request, BatchLiquidationService $service): JsonResponse
    {
        $validated = $request->validate([
            'payer_id' => ['required', 'integer', 'exists:afl_payers,id'],
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        try {
            $batch = $service->createDraft(
                (int) $validated['payer_id'],
                (int) $validated['period_year'],
                (int) $validated['period_month'],
                $request->user()?->email,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->batchToArray($batch), 201);
    }

    /**
     * Detalle de un lote con todas sus líneas.
     */
    public function show(int $batchId): JsonResponse
    {
        $batch = LiquidationBatch::query()
            ->with(['payer', 'lines.affiliate.person', 'entitySummaries'])
            ->findOrFail($batchId);

        return response()->json($this->batchToArray($batch));
    }

    /**
     * Lista de lotes (paginada, con filtro por pagador).
     */
    public function index(Request $request): JsonResponse
    {
        $query = LiquidationBatch::query()->with('payer')->orderByDesc('id');

        if ($request->has('payer_id')) {
            $query->where('payer_id', (int) $request->input('payer_id'));
        }
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $batches = $query->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => collect($batches->items())->map(fn ($b) => [
                'id' => $b->id,
                'payer' => $b->payer ? [
                    'id' => $b->payer->id,
                    'razon_social' => $b->payer->razon_social,
                    'nit' => $b->payer->nit,
                ] : null,
                'period' => "{$b->period_year}-".str_pad($b->period_month, 2, '0', STR_PAD_LEFT),
                'status' => $b->status,
                'cant_affiliates' => $b->cant_affiliates,
                'grand_total' => $b->grand_total,
                'created_at' => $b->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'total' => $batches->total(),
                'currentPage' => $batches->currentPage(),
                'lastPage' => $batches->lastPage(),
            ],
        ]);
    }

    /**
     * Actualiza una línea del lote (edición manual).
     */
    public function updateLine(
        Request $request,
        int $batchId,
        int $lineId,
        BatchLiquidationService $service,
    ): JsonResponse {
        $line = LiquidationBatchLine::query()
            ->where('batch_id', $batchId)
            ->findOrFail($lineId);

        $validated = $request->validate([
            'salary' => ['nullable', 'integer', 'min:0'],
            'days_eps' => ['nullable', 'integer', 'min:1', 'max:30'],
            'days_afp' => ['nullable', 'integer', 'min:0', 'max:30'],
            'days_arl' => ['nullable', 'integer', 'min:0', 'max:30'],
            'days_ccf' => ['nullable', 'integer', 'min:0', 'max:30'],
            'contributor_type_code' => ['nullable', 'string', 'max:3'],
            'arl_risk_class' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        try {
            $updated = $service->recalculateLine($line, array_filter($validated, fn ($v) => $v !== null));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->lineToArray($updated->load('affiliate.person')));
    }

    /**
     * Excluir/incluir una línea del lote.
     */
    public function toggleLine(int $batchId, int $lineId, BatchLiquidationService $service): JsonResponse
    {
        $line = LiquidationBatchLine::query()
            ->where('batch_id', $batchId)
            ->findOrFail($lineId);

        try {
            $updated = $service->toggleLineStatus($line);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->lineToArray($updated->load('affiliate.person')));
    }

    /**
     * Confirma el lote — BORRADOR → LIQUIDADO.
     */
    public function confirm(int $batchId, BatchLiquidationService $service): JsonResponse
    {
        $batch = LiquidationBatch::query()->findOrFail($batchId);

        try {
            $confirmed = $service->confirm($batch);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->batchToArray($confirmed->load(['lines.affiliate.person', 'payer', 'entitySummaries'])));
    }

    /**
     * Cancela un lote.
     */
    public function cancel(int $batchId, BatchLiquidationService $service): JsonResponse
    {
        $batch = LiquidationBatch::query()->findOrFail($batchId);

        try {
            $cancelled = $service->cancel($batch);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $cancelled->id, 'status' => $cancelled->status]);
    }

    /** @return array<string, mixed> */
    private function batchToArray(LiquidationBatch $b): array
    {
        $b->loadMissing(['payer', 'lines.affiliate.person', 'entitySummaries']);

        return [
            'id' => $b->id,
            'payer' => $b->payer ? [
                'id' => $b->payer->id,
                'razon_social' => $b->payer->razon_social,
                'nit' => $b->payer->nit,
            ] : null,
            'period_year' => $b->period_year,
            'period_month' => $b->period_month,
            'status' => $b->status,
            'planilla_type' => $b->planilla_type,
            'planilla_number' => $b->planilla_number,
            'totals' => [
                'health' => $b->total_health,
                'pension' => $b->total_pension,
                'arl' => $b->total_arl,
                'ccf' => $b->total_ccf,
                'solidarity' => $b->total_solidarity,
                'admin' => $b->total_admin,
                'grand_total' => $b->grand_total,
            ],
            'cant_affiliates' => $b->cant_affiliates,
            'rounding_adjustment' => $b->rounding_adjustment_total,
            'lines' => $b->lines->map(fn ($l) => $this->lineToArray($l))->values()->all(),
            'entity_summaries' => $b->entitySummaries->map(fn ($e) => [
                'entity_code' => $e->entity_pila_code,
                'subsystem' => $e->subsystem,
                'amount' => $e->amount_pesos,
            ])->values()->all(),
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function lineToArray(LiquidationBatchLine $l): array
    {
        return [
            'id' => $l->id,
            'affiliate_id' => $l->affiliate_id,
            'affiliate_name' => trim(
                ($l->affiliate?->person?->first_name ?? '').' '.
                ($l->affiliate?->person?->first_surname ?? '')
            ),
            'document_number' => $l->affiliate?->person?->document_number,
            'contributor_type' => $l->contributor_type_code,
            'salary' => $l->salary,
            'ibc' => $l->ibc,
            'days_eps' => $l->days_eps,
            'days_afp' => $l->days_afp,
            'days_arl' => $l->days_arl,
            'days_ccf' => $l->days_ccf,
            'health_total' => $l->health_total,
            'pension_total' => $l->pension_total,
            'arl_total' => $l->arl_total,
            'ccf_total' => $l->ccf_total,
            'solidarity' => $l->solidarity,
            'admin_fee' => $l->admin_fee,
            'interest_mora' => $l->interest_mora,
            'total_ss' => $l->total_ss,
            'total_payable' => $l->total_payable,
            'line_status' => $l->line_status,
        ];
    }
}
