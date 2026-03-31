<?php

namespace App\Modules\RegulatoryEngine\Services;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\RegulatoryEngine\Strategies\StrategyResolver;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Carbon\Carbon;

/**
 * Determina el período de cotización para un afiliado — RN-07, RF-051, RF-052.
 *
 * Dependientes (pago VENCIDO): cotización mes X → pago mes X+1.
 * Independientes (pago ACTUAL): cotización mes X → pago mes X.
 *
 * Portado de Access Form_005 ValidaPeriodo / IdEmpleador.
 *
 * @see DOCUMENTO_RECTOR §3.7, RF-051, RF-052
 */
final class PeriodDeterminationService
{
    public function __construct(
        private readonly StrategyResolver $strategyResolver,
    ) {}

    /**
     * Determina el período y los días de cotización.
     *
     * @return array{
     *     period: Periodo,
     *     days: int,
     *     is_first_contribution: bool,
     *     is_advance_period: bool,
     *     novelty_ing: bool,
     *     enrollment_date: Carbon|null,
     * }
     */
    public function determine(Affiliate $affiliate, string $contributorTypeCode): array
    {
        $isCurrentPeriod = $this->strategyResolver->isCurrentPeriod($contributorTypeCode);
        $now = Carbon::now();

        $lastPaidPeriod = $this->lastPaidPeriod($affiliate);

        if ($lastPaidPeriod === null) {
            return $this->firstContribution($affiliate, $isCurrentPeriod, $now);
        }

        return $this->subsequentContribution($lastPaidPeriod, $isCurrentPeriod, $now);
    }

    /**
     * Primer aporte (sin pagos anteriores) — Caso 7 del Rector.
     * Período = mes siguiente al de ingreso con días proporcionales + novedad ING.
     */
    private function firstContribution(Affiliate $affiliate, bool $isCurrentPeriod, Carbon $now): array
    {
        $enrollmentDate = $this->enrollmentDate($affiliate);

        if ($enrollmentDate === null) {
            $enrollmentDate = $now;
        }

        $period = Periodo::fromDate($enrollmentDate);

        // Días proporcionales: 31 - día_ingreso (Portado de Form_005 IdEmpleador)
        $days = 31 - $enrollmentDate->day;
        if ($days > 30) {
            $days = 30;
        }
        if ($days < 1) {
            $days = 1;
        }

        $currentPeriod = $isCurrentPeriod
            ? Periodo::fromDate($now)
            : Periodo::fromDate($now)->anterior();

        $isAdvance = $period->isAfter($currentPeriod);

        return [
            'period' => $period,
            'days' => $days,
            'is_first_contribution' => true,
            'is_advance_period' => $isAdvance,
            'novelty_ing' => true,
            'enrollment_date' => $enrollmentDate,
        ];
    }

    /**
     * Aportes subsiguientes — período siguiente al último pagado, 30 días.
     */
    private function subsequentContribution(Periodo $lastPaid, bool $isCurrentPeriod, Carbon $now): array
    {
        $nextPeriod = $lastPaid->siguiente();

        $currentPeriod = $isCurrentPeriod
            ? Periodo::fromDate($now)
            : Periodo::fromDate($now)->anterior();

        $isAdvance = $nextPeriod->isAfter($currentPeriod);

        return [
            'period' => $nextPeriod,
            'days' => 30,
            'is_first_contribution' => false,
            'is_advance_period' => $isAdvance,
            'novelty_ing' => false,
            'enrollment_date' => null,
        ];
    }

    /** Último período pagado desde las líneas de liquidaciones PILA confirmadas. */
    private function lastPaidPeriod(Affiliate $affiliate): ?Periodo
    {
        $last = PilaLiquidation::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereIn('status', ['confirmed', 'LIQUIDADO', 'PAGADO'])
            ->join('pila_liquidation_lines', 'pila_liquidations.id', '=', 'pila_liquidation_lines.pila_liquidation_id')
            ->orderByDesc('pila_liquidation_lines.period_year')
            ->orderByDesc('pila_liquidation_lines.period_month')
            ->select('pila_liquidation_lines.period_year', 'pila_liquidation_lines.period_month')
            ->first();

        if ($last === null) {
            return null;
        }

        return new Periodo((int) $last->period_year, (int) $last->period_month);
    }

    /** Fecha de afiliación (desde el vínculo pagador vigente). */
    private function enrollmentDate(Affiliate $affiliate): ?Carbon
    {
        $payer = $affiliate->currentAffiliatePayer;

        if ($payer?->start_date) {
            return Carbon::parse($payer->start_date);
        }

        return $affiliate->created_at ? Carbon::parse($affiliate->created_at) : null;
    }
}
