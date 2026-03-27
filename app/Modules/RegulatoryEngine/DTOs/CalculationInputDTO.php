<?php

namespace App\Modules\RegulatoryEngine\DTOs;

use App\Modules\RegulatoryEngine\ValueObjects\Periodo;

/** Entrada mínima para liquidación PILA (se ampliará con novedades, perfiles SS, etc.). */
final readonly class CalculationInputDTO
{
    public function __construct(
        public int $rawIbcPesos,
        public Periodo $cotizationPeriod,
        public string $contributorTypeCode,
        public int $arlRiskClass = 1,
    ) {}
}
