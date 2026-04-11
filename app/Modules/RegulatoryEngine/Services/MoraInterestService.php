<?php

namespace App\Modules\RegulatoryEngine\Services;

/**
 * Intereses de mora — Portado de Access Form_005 línea 10828.
 *
 * Fórmula Rector §8.3, RN-11, RN-13:
 *   Interés = Round((((TotalAportePOS/30) × 0.025) × DíasMora) / 100, 0) × 100
 *
 * Base = salud + pensión + ARL + CCF + solidaridad (NO incluye admin ni afiliación).
 * Tasa = 0.0833%/día (2.5%/mes) — parametrizable por aportante.
 *
 * @see DOCUMENTO_RECTOR §3.6, §8.3, RF-039, RF-088
 */
final class MoraInterestService
{
    /**
     * Calcula interés de mora sobre la base de aportes SS.
     *
     * @param  int  $totalAportePOSPesos  Base: salud+pensión+ARL+CCF+solidaridad
     * @param  int  $daysLate  Días de mora (0 = sin mora)
     * @param  bool  $moraExempt  Excepción operativa: sin intereses
     * @param  float  $monthlyRatePercent  Tasa mensual (default 2.5%)
     */
    public function interestPesos(
        int $totalAportePOSPesos,
        int $daysLate,
        bool $moraExempt = false,
        float $monthlyRatePercent = 2.5,
    ): int {
        if ($moraExempt || $daysLate <= 0 || $totalAportePOSPesos <= 0) {
            return 0;
        }

        // Portado de Form_005: Round((((Base/30) × tasaMensual/100) × DíasMora) / 100, 0) × 100
        $dailyBase = $totalAportePOSPesos / 30;
        $monthlyRate = $monthlyRatePercent / 100;
        $rawInterest = ($dailyBase * $monthlyRate * $daysLate);

        return (int) (round($rawInterest / 100, 0) * 100);
    }
}
