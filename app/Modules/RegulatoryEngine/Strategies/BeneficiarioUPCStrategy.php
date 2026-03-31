<?php

namespace App\Modules\RegulatoryEngine\Strategies;

use App\Modules\RegulatoryEngine\DTOs\CalculationContext;
use App\Modules\RegulatoryEngine\Enums\SubsystemType;

/**
 * Beneficiario UPC — tipo 40.
 *
 * Solo paga UPC (salud). Sin pensión, ARL ni CCF.
 *
 * @see DOCUMENTO_RECTOR §3.2, RF-042
 */
final class BeneficiarioUPCStrategy implements ContributorCalculationStrategy
{
    public function supportedCodes(): array
    {
        return ['40'];
    }

    public function applicableSubsystems(): array
    {
        return [SubsystemType::SALUD];
    }

    public function calculate(CalculationContext $context): array
    {
        $ibcSalud = $context->ibcSalud();

        return [
            'ibc_salud_pesos' => $ibcSalud->valueInPesos,
            'ibc_pension_pesos' => 0,
            'health_total_pesos' => $ibcSalud->valueInPesos, // UPC = IBC completo
            'pension_total_pesos' => 0,
            'arl_total_pesos' => 0,
            'ccf_total_pesos' => 0,
            'admin_fee_pesos' => 0,
        ];
    }

    public function isCurrentPeriod(): bool
    {
        return false;
    }
}
