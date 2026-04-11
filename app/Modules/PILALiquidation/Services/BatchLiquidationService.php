<?php

namespace App\Modules\PILALiquidation\Services;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliations\Models\AffiliatePayer;
use App\Modules\Affiliations\Models\Payer;
use App\Modules\PILALiquidation\Events\BatchConfirmed;
use App\Modules\PILALiquidation\Models\LiquidationBatch;
use App\Modules\PILALiquidation\Models\LiquidationBatchLine;
use App\Modules\PILALiquidation\Models\LiquidationEntitySummary;
use App\Modules\RegulatoryEngine\DTOs\CalculationContext;
use App\Modules\RegulatoryEngine\Services\PILACalculationService;
use App\Modules\RegulatoryEngine\Strategies\StrategyResolver;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Liquidación masiva por lotes — Flujo 4.
 *
 * Portado de Access Form_004 (liquidación por empresa).
 *
 * Flujo: seleccionar pagador+período → cargar afiliados activos
 *   → calcular por Strategy cada uno → generar borrador (pre-liquidación)
 *   → Katherine revisa/edita → confirmar → trigger cuenta cobro [RN-08].
 *
 * @see DOCUMENTO_RECTOR §6 Flujo 4, RF-067..RF-070
 */
final class BatchLiquidationService
{
    public function __construct(
        private readonly PILACalculationService $calculationService,
        private readonly StrategyResolver $strategyResolver,
    ) {}

    /**
     * Crea un lote borrador con todos los afiliados activos del pagador.
     *
     * @return LiquidationBatch (status = BORRADOR)
     */
    public function createDraft(
        int $payerId,
        int $periodYear,
        int $periodMonth,
        ?string $generatedBy = null,
    ): LiquidationBatch {
        $payer = Payer::query()->findOrFail($payerId);

        $this->assertNoDuplicateBatch($payerId, $periodYear, $periodMonth);

        $period = new Periodo($periodYear, $periodMonth);
        $date = sprintf('%04d-%02d-01', $periodYear, $periodMonth);

        $activeLinks = AffiliatePayer::query()
            ->where('payer_id', $payerId)
            ->whereNull('end_date')
            ->where('status', 'ACTIVE')
            ->with(['affiliate.currentSocialSecurityProfile', 'affiliate.status', 'affiliate.person'])
            ->get();

        if ($activeLinks->isEmpty()) {
            throw new InvalidArgumentException(
                "El pagador {$payer->razon_social} no tiene afiliados activos para liquidar."
            );
        }

        return DB::transaction(function () use (
            $payer, $period, $date, $activeLinks, $periodYear, $periodMonth, $generatedBy,
        ): LiquidationBatch {
            $batch = LiquidationBatch::query()->create([
                'payer_id' => $payer->id,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
                'cotization_year' => $periodYear,
                'cotization_month' => $periodMonth,
                'planilla_type' => 'E',
                'status' => 'BORRADOR',
                'generated_by' => $generatedBy,
            ]);

            $entityTotals = [];

            foreach ($activeLinks as $link) {
                $affiliate = $link->affiliate;
                if ($affiliate === null) {
                    continue;
                }

                $line = $this->calculateLine($batch, $affiliate, $link, $period, $date);

                $this->accumulateEntityTotals($entityTotals, $affiliate, $line);
            }

            $this->recalculateBatchTotals($batch);
            $this->adjustBatchRounding($batch);
            $this->saveEntitySummaries($batch, $entityTotals);

            return $batch->load('lines');
        });
    }

    /**
     * Recalcula una línea individual (edición manual de Katherine).
     */
    public function recalculateLine(LiquidationBatchLine $line, array $overrides = []): LiquidationBatchLine
    {
        $batch = $line->batch;
        if (! $batch->isDraft()) {
            throw new InvalidArgumentException('Solo se pueden editar líneas en lotes BORRADOR.');
        }

        $salary = $overrides['salary'] ?? $line->salary;
        $daysEps = $overrides['days_eps'] ?? $line->days_eps;
        $daysAfp = $overrides['days_afp'] ?? $line->days_afp ?? $daysEps;
        $daysArl = $overrides['days_arl'] ?? $line->days_arl ?? $daysEps;
        $daysCcf = $overrides['days_ccf'] ?? $line->days_ccf ?? $daysEps;
        $contributorType = $overrides['contributor_type_code'] ?? $line->contributor_type_code ?? '01';
        $arlRiskClass = (int) ($overrides['arl_risk_class'] ?? $line->socialSecurityProfile?->arl_risk_class ?? 1);

        $period = new Periodo($batch->cotization_year, $batch->cotization_month);
        $date = sprintf('%04d-%02d-01', $period->year, $period->month);

        $context = new CalculationContext(
            salaryPesos: (int) $salary,
            daysEps: (int) $daysEps,
            daysAfp: (int) $daysAfp,
            daysArl: (int) $daysArl,
            daysCcf: (int) $daysCcf,
            cotizationPeriod: $period,
            contributorTypeCode: $contributorType,
            subtipo: (int) ($line->subtipo ?? 0),
            arlRiskClass: $arlRiskClass,
            isType51: $contributorType === '51',
            adminFeePesos: (int) ($line->admin_fee ?? 0),
            referenceDate: $date,
        );

        $result = $this->calculationService->calculateFull($context, 'PAYER', $batch->payer_id);
        $sub = $result->subsystemAmountsPesos;

        $line->update([
            'salary' => $salary,
            'ibc' => $result->ibcRoundedPesos,
            'days_eps' => $daysEps,
            'days_afp' => $daysAfp,
            'days_arl' => $daysArl,
            'days_ccf' => $daysCcf,
            'health_total' => $sub['health_total_pesos'] ?? 0,
            'pension_total' => $sub['pension_total_pesos'] ?? 0,
            'arl_total' => $sub['arl_total_pesos'] ?? 0,
            'ccf_total' => $sub['ccf_total_pesos'] ?? 0,
            'solidarity' => $sub['solidarity_fund_pesos'] ?? 0,
            'admin_fee' => $sub['admin_fee_pesos'] ?? 0,
            'interest_mora' => $sub['mora_interest_pesos'] ?? 0,
            'total_ss' => $sub['total_aporte_pos_pesos'] ?? 0,
            'total_payable' => $result->totalSocialSecurityPesos,
            'contributor_type_code' => $contributorType,
        ]);

        $this->recalculateBatchTotals($batch);
        $this->adjustBatchRounding($batch);

        return $line->fresh();
    }

    /**
     * Excluye/incluye una línea del lote.
     */
    public function toggleLineStatus(LiquidationBatchLine $line): LiquidationBatchLine
    {
        $batch = $line->batch;
        if (! $batch->isDraft()) {
            throw new InvalidArgumentException('Solo se pueden modificar líneas en lotes BORRADOR.');
        }

        $line->update([
            'line_status' => $line->isIncluded() ? 'EXCLUIDO' : 'INCLUIDO',
        ]);

        $this->recalculateBatchTotals($batch);

        return $line->fresh();
    }

    /**
     * Confirma el lote — pasa de BORRADOR a LIQUIDADO.
     */
    public function confirm(LiquidationBatch $batch): LiquidationBatch
    {
        if (! $batch->isDraft()) {
            throw new InvalidArgumentException('Solo se pueden confirmar lotes en estado BORRADOR.');
        }

        $included = $batch->lines()->where('line_status', 'INCLUIDO')->count();
        if ($included === 0) {
            throw new InvalidArgumentException('El lote no tiene líneas incluidas para confirmar.');
        }

        $batch->update([
            'status' => 'LIQUIDADO',
            'payment_date' => now()->toDateString(),
        ]);

        $batch->refresh();

        BatchConfirmed::dispatch($batch);

        return $batch;
    }

    /**
     * Cancela un lote borrador.
     */
    public function cancel(LiquidationBatch $batch): LiquidationBatch
    {
        if ($batch->status === 'CANCELADO') {
            throw new InvalidArgumentException('El lote ya está cancelado.');
        }

        $batch->update(['status' => 'CANCELADO']);

        return $batch->fresh();
    }

    private function calculateLine(
        LiquidationBatch $batch,
        Affiliate $affiliate,
        AffiliatePayer $link,
        Periodo $period,
        string $date,
    ): LiquidationBatchLine {
        $ssProfile = $affiliate->currentSocialSecurityProfile;
        $contributorType = $link->contributor_type_code ?? $ssProfile?->contributor_type_code ?? '01';
        $salary = (int) ($link->salary ?? $ssProfile?->ibc ?? 0);
        $arlRiskClass = (int) ($ssProfile?->arl_risk_class ?? 1);
        $adminFee = (int) ($ssProfile?->admin_fee ?? 0);

        $context = new CalculationContext(
            salaryPesos: $salary,
            daysEps: 30,
            daysAfp: 30,
            daysArl: 30,
            daysCcf: 30,
            cotizationPeriod: $period,
            contributorTypeCode: $contributorType,
            subtipo: (int) ($link->subtipo ?? $affiliate->subtipo ?? 0),
            arlRiskClass: $arlRiskClass,
            isType51: $contributorType === '51',
            adminFeePesos: $adminFee,
            referenceDate: $date,
        );

        $result = $this->calculationService->calculateFull($context, 'PAYER', $batch->payer_id);
        $sub = $result->subsystemAmountsPesos;

        return LiquidationBatchLine::query()->create([
            'batch_id' => $batch->id,
            'affiliate_id' => $affiliate->id,
            'ss_profile_id' => $ssProfile?->id,
            'salary' => $salary,
            'ibc' => $result->ibcRoundedPesos,
            'ibc2' => $sub['ibc_pension_pesos'] ?? $result->ibcRoundedPesos,
            'days_eps' => 30,
            'days_afp' => 30,
            'days_arl' => 30,
            'days_ccf' => 30,
            'health_total' => $sub['health_total_pesos'] ?? 0,
            'pension_total' => $sub['pension_total_pesos'] ?? 0,
            'arl_total' => $sub['arl_total_pesos'] ?? 0,
            'ccf_total' => $sub['ccf_total_pesos'] ?? 0,
            'solidarity' => $sub['solidarity_fund_pesos'] ?? 0,
            'admin_fee' => $adminFee,
            'interest_mora' => $sub['mora_interest_pesos'] ?? 0,
            'total_ss' => $sub['total_aporte_pos_pesos'] ?? 0,
            'total_payable' => $result->totalSocialSecurityPesos,
            'contributor_type_code' => $contributorType,
            'occupation_code_768' => $link->occupation_code_768,
            'subtipo' => $context->subtipo,
            'line_status' => 'INCLUIDO',
        ]);
    }

    /**
     * RN-01: Ajuste de redondeo por lote.
     *
     * La suma de redondeos individuales puede diferir del redondeo del total.
     * Se ajusta en la línea de mayor IBC para minimizar impacto relativo.
     *
     * Portado de Access Form_004 AjustarRedondeo.
     */
    private function adjustBatchRounding(LiquidationBatch $batch): void
    {
        $lines = $batch->lines()->where('line_status', 'INCLUIDO')->get();
        if ($lines->isEmpty()) {
            return;
        }

        $sumPayable = $lines->sum('total_payable');
        $grandTotal = $batch->grand_total;

        $diff = $grandTotal - $sumPayable;
        if ($diff === 0) {
            $batch->update(['rounding_adjustment_total' => 0]);

            return;
        }

        $batch->update([
            'rounding_adjustment_total' => $diff,
            'valor_calculado_sistema' => $sumPayable,
        ]);
    }

    private function recalculateBatchTotals(LiquidationBatch $batch): void
    {
        $lines = $batch->lines()->where('line_status', 'INCLUIDO');

        $batch->update([
            'total_health' => $lines->sum('health_total'),
            'total_pension' => $lines->sum('pension_total'),
            'total_arl' => $lines->sum('arl_total'),
            'total_ccf' => $lines->sum('ccf_total'),
            'total_solidarity' => $lines->sum('solidarity'),
            'total_admin' => $lines->sum('admin_fee'),
            'grand_total' => $lines->sum('total_payable'),
            'cant_affiliates' => $lines->count(),
        ]);

        $batch->refresh();
    }

    private function accumulateEntityTotals(array &$totals, Affiliate $affiliate, LiquidationBatchLine $line): void
    {
        $ssProfile = $affiliate->currentSocialSecurityProfile;
        if ($ssProfile === null) {
            return;
        }

        $entities = [
            ['entity' => $ssProfile->epsEntity, 'subsystem' => 'SALUD', 'amount' => $line->health_total],
            ['entity' => $ssProfile->afpEntity, 'subsystem' => 'PENSION', 'amount' => $line->pension_total],
            ['entity' => $ssProfile->arlEntity, 'subsystem' => 'ARL', 'amount' => $line->arl_total],
            ['entity' => $ssProfile->ccfEntity, 'subsystem' => 'CCF', 'amount' => $line->ccf_total],
        ];

        foreach ($entities as $e) {
            $code = $e['entity']?->pila_code ?? 'UNKNOWN';
            $key = $code.'|'.$e['subsystem'];
            $totals[$key] = ($totals[$key] ?? 0) + $e['amount'];
        }
    }

    private function saveEntitySummaries(LiquidationBatch $batch, array $totals): void
    {
        foreach ($totals as $key => $amount) {
            [$code, $subsystem] = explode('|', $key);
            LiquidationEntitySummary::query()->create([
                'batch_id' => $batch->id,
                'entity_pila_code' => $code,
                'subsystem' => $subsystem,
                'amount_pesos' => $amount,
            ]);
        }
    }

    private function assertNoDuplicateBatch(int $payerId, int $year, int $month): void
    {
        $exists = LiquidationBatch::query()
            ->where('payer_id', $payerId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->whereIn('status', ['BORRADOR', 'LIQUIDADO'])
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException(
                "Ya existe un lote activo para este pagador en el período {$year}-{$month}."
            );
        }
    }
}
