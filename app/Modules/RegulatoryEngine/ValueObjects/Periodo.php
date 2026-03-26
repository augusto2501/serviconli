<?php

namespace App\Modules\RegulatoryEngine\ValueObjects;

final readonly class Periodo
{
    public function __construct(
        public int $year,
        public int $month,
    ) {
        if ($year < 1970 || $year > 2100) {
            throw new \InvalidArgumentException('Año fuera de rango.');
        }
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Mes inválido.');
        }
    }
}
