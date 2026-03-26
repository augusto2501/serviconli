<?php

namespace App\Modules\RegulatoryEngine\Services;

use App\Modules\RegulatoryEngine\DTOs\CalculationInputDTO;
use App\Modules\RegulatoryEngine\DTOs\CalculationResultDTO;
use App\Modules\RegulatoryEngine\ValueObjects\IBC;

/**
 * Orquestador de liquidación PILA. Las tarifas y reglas por tipo de cotizante
 * vendrán de cfg_* y estrategias; aquí solo la base IBC redondeada (Fase 1).
 */
final class PILACalculationService
{
    public function calculate(CalculationInputDTO $input): CalculationResultDTO
    {
        $ibc = IBC::fromRaw($input->rawIbcPesos)->roundToMillarSuperior();

        return new CalculationResultDTO(
            ibcRoundedPesos: $ibc->valueInPesos,
            subsystemAmountsPesos: [],
            totalSocialSecurityPesos: 0,
        );
    }
}
