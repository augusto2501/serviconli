<?php

namespace App\Modules\RegulatoryEngine\DTOs;

/** Varios períodos liquidados en una sola operación (totales = suma de líneas). */
final readonly class ConsolidatedCalculationResultDTO
{
    /**
     * @param  list<ConsolidatedCalculationLineDTO>  $lines
     * @param  array<string, int>  $subsystemTotalsPesos  Suma por clave de montos en pesos.
     */
    public function __construct(
        public array $lines,
        public int $totalSocialSecurityPesos,
        public array $subsystemTotalsPesos = [],
    ) {}
}
