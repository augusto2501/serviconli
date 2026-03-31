<?php

namespace App\Modules\RegulatoryEngine\Strategies;

use App\Modules\RegulatoryEngine\DTOs\CalculationContext;
use App\Modules\RegulatoryEngine\Enums\SubsystemType;
use App\Modules\RegulatoryEngine\Services\RoundingEngine;
use App\Modules\RegulatoryEngine\ValueObjects\MontoAporte;

/**
 * Dependiente general — tipos 01 (Dependiente) y 02 (Servicio doméstico).
 *
 * Subsistemas: S + P + ARL + CCF (4%).
 * Pago VENCIDO (mes siguiente al de cotización).
 *
 * @see DOCUMENTO_RECTOR §3.2, RF-042
 */
final class DependienteGeneralStrategy implements ContributorCalculationStrategy
{
    public function supportedCodes(): array
    {
        return ['01', '02'];
    }

    public function applicableSubsystems(): array
    {
        return [SubsystemType::SALUD, SubsystemType::PENSION, SubsystemType::ARL, SubsystemType::CCF];
    }

    public function calculate(CalculationContext $context): array
    {
        $ibcSalud = $context->ibcSalud();
        $ibcPension = $context->ibcPension();

        $health = MontoAporte::calcular($ibcSalud, $context->healthRatePercent);
        $pension = MontoAporte::calcular($ibcPension, $context->pensionRatePercent);
        $arl = MontoAporte::calcularARL($ibcSalud, $context->arlRatePercent);
        $ccf = MontoAporte::calcular($ibcSalud, $context->ccfRatePercent);

        // Fee admin proporcional — RN-04: roundLegacy(Round((Admin/30) × DíasEPS, 0))
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
            'arl_total_pesos' => $arl->pesos,
            'ccf_total_pesos' => $ccf->pesos,
            'admin_fee_pesos' => $adminFee,
        ];
    }

    public function isCurrentPeriod(): bool
    {
        return false; // Pago VENCIDO
    }
}
