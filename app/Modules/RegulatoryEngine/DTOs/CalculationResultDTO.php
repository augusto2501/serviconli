<?php

namespace App\Modules\RegulatoryEngine\DTOs;

/** Resultado parcial de cálculo; los subsistemas y totales se completarán con estrategias. */
final readonly class CalculationResultDTO
{
    public function __construct(
        public int $ibcRoundedPesos,
        public array $subsystemAmountsPesos = [],
        public int $totalSocialSecurityPesos = 0,
    ) {}
}
