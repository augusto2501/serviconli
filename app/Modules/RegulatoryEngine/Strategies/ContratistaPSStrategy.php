<?php

namespace App\Modules\RegulatoryEngine\Strategies;

use App\Modules\RegulatoryEngine\DTOs\CalculationContext;
use App\Modules\RegulatoryEngine\Enums\SubsystemType;
use App\Modules\RegulatoryEngine\Services\RoundingEngine;
use App\Modules\RegulatoryEngine\ValueObjects\MontoAporte;

/**
 * Contratista prestación de servicios — tipo 59.
 *
 * Subsistemas: S + P. Sin ARL ni CCF.
 * Pago ACTUAL.
 *
 * @see DOCUMENTO_RECTOR §3.2, RF-042
 */
final class ContratistaPSStrategy implements ContributorCalculationStrategy
{
    public function supportedCodes(): array
    {
        return ['59'];
    }

    public function applicableSubsystems(): array
    {
        return [SubsystemType::SALUD, SubsystemType::PENSION];
    }

    public function calculate(CalculationContext $context): array
    {
        $ibcSalud = $context->ibcSalud();
        $ibcPension = $context->ibcPension();

        $health = MontoAporte::calcular($ibcSalud, $context->healthRatePercent);
        $pension = MontoAporte::calcular($ibcPension, $context->pensionRatePercent);

        $adminFee = 0;
        if ($context->adminFeePesos > 0) {
            $adminFee = RoundingEngine::roundLegacy(
                (int) round(($context->adminFeePesos / 30) * $context->daysEps, 0)
            );
        }

        return [
            'ibc_salud_pesos' => $ibcSalud->valueInPesos,
            'ibc_pension_pesos' => $ibcPension->valueInPesos,
            'health_total_pesos' => $health->pesos,
            'pension_total_pesos' => $pension->pesos,
            'arl_total_pesos' => 0,
            'ccf_total_pesos' => 0,
            'admin_fee_pesos' => $adminFee,
        ];
    }

    public function isCurrentPeriod(): bool
    {
        return true; // Pago ACTUAL
    }
}
