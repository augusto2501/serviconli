<?php

namespace App\Modules\RegulatoryEngine\Strategies;

use App\Modules\RegulatoryEngine\DTOs\CalculationContext;
use App\Modules\RegulatoryEngine\Enums\SubsystemType;
use App\Modules\RegulatoryEngine\Services\RoundingEngine;
use App\Modules\RegulatoryEngine\ValueObjects\MontoAporte;

/**
 * Independiente general — tipos 03 (Independiente), 16 (?), 57 (Indep. voluntario ARL).
 *
 * Subsistemas: S + P + ARL. CCF al 2%.
 * IBC = 40% del ingreso reportado (RF-030).
 * Pago ACTUAL (mes de cotización = mes de pago).
 *
 * @see DOCUMENTO_RECTOR §3.2, RF-042, D.1273/2018
 */
final class IndependienteGeneralStrategy implements ContributorCalculationStrategy
{
    public function supportedCodes(): array
    {
        return ['03', '16', '57'];
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
        return true; // Pago ACTUAL
    }
}
