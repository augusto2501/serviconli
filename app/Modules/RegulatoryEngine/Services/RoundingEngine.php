<?php

namespace App\Modules\RegulatoryEngine\Services;

use App\Modules\RegulatoryEngine\Enums\RoundingMethod;

final class RoundingEngine
{
    /**
     * Redondea un monto entero en pesos según el contexto.
     */
    public function round(int $amountPesos, RoundingMethod $method): int
    {
        return match ($method) {
            RoundingMethod::IBC => $amountPesos <= 0 ? 0 : (int) ceil($amountPesos / 1000) * 1000,
            RoundingMethod::LEGACY => $amountPesos,
            RoundingMethod::PILA => $amountPesos,
        };
    }
}
