<?php

namespace App\Modules\RegulatoryEngine\ValueObjects;

/** Clase de riesgo ARL I–V. */
final readonly class ClaseRiesgo
{
    public function __construct(public int $nivel)
    {
        if ($nivel < 1 || $nivel > 5) {
            throw new \InvalidArgumentException('Clase de riesgo debe ser 1–5.');
        }
    }
}
