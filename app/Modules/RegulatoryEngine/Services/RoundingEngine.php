<?php

namespace App\Modules\RegulatoryEngine\Services;

use App\Modules\RegulatoryEngine\Enums\RoundingMethod;

/**
 * Motor de redondeo centralizado — Portado de Access Form_005.
 *
 * @see DOCUMENTO_RECTOR §3.1, RF-043
 */
final class RoundingEngine
{
    /**
     * Redondea un monto entero en pesos según el contexto.
     */
    public function round(int $amountPesos, RoundingMethod $method): int
    {
        if ($amountPesos <= 0) {
            return 0;
        }

        return match ($method) {
            RoundingMethod::IBC => self::roundIBC($amountPesos),
            RoundingMethod::LEGACY => self::roundLegacy($amountPesos),
            RoundingMethod::PILA => (int) round($amountPesos, 0, PHP_ROUND_HALF_UP),
        };
    }

    /** RN-01: IBC al millar superior (mod 1000). Portado de Form_005 línea 11262. */
    public static function roundIBC(int $v): int
    {
        if ($v <= 0) {
            return 0;
        }
        $remainder = $v % 1000;

        return $remainder > 0 ? $v + (1000 - $remainder) : $v;
    }

    /** Redondeo legacy Access: centenar superior (mod 100). Portado de Form_005 CalculaAportes. */
    public static function roundLegacy(int $v): int
    {
        if ($v <= 0) {
            return 0;
        }
        $remainder = $v % 100;

        return $remainder > 0 ? $v + (100 - $remainder) : $v;
    }

    /** Validación cruzada PILA estándar — PHP_ROUND_HALF_UP. */
    public static function roundPILA(int $v): int
    {
        return (int) round($v, 0, PHP_ROUND_HALF_UP);
    }
}
