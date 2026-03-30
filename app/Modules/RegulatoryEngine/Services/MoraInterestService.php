<?php

namespace App\Modules\RegulatoryEngine\Services;

// DOCUMENTO_RECTOR §3.6 — intereses de mora (tasa diaria desde cfg_regulatory_parameters)

final class MoraInterestService
{
    public function interestPesos(int $ibcRoundedPesos, int $daysLate, bool $moraExempt, float $moraRatePercent): int
    {
        if ($moraExempt || $daysLate <= 0) {
            return 0;
        }

        $dailyRate = $moraRatePercent / 100;

        return (int) round($ibcRoundedPesos * $dailyRate * $daysLate);
    }
}
