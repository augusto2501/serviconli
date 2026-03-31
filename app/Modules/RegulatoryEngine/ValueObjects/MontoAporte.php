<?php

namespace App\Modules\RegulatoryEngine\ValueObjects;

use App\Modules\RegulatoryEngine\Services\RoundingEngine;

/**
 * Monto de aporte en pesos COP (entero).
 *
 * Portado de Access Form_005 CalculaAportes.
 *
 * @see DOCUMENTO_RECTOR §2.3, RF-032
 */
final readonly class MontoAporte
{
    public function __construct(public int $pesos) {}

    public static function fromInt(int $pesos): self
    {
        return new self(max(0, $pesos));
    }

    /**
     * Calcula monto de aporte = roundLegacy(IBC × tasa).
     * Para ARL: roundLegacy(Round(IBC × tasa, 0)) — se usa roundPHP primero.
     */
    public static function calcular(IBC $ibc, float $tasaPercent): self
    {
        $raw = intval($ibc->valueInPesos * ($tasaPercent / 100));

        return new self(RoundingEngine::roundLegacy($raw));
    }

    /**
     * Calcula ARL: roundLegacy(Round(IBC × tasa, 0)).
     * Portado de Form_005: doble redondeo (Round PHP + legacy centenar).
     */
    public static function calcularARL(IBC $ibc, float $tasaPercent): self
    {
        $rounded = (int) round($ibc->valueInPesos * ($tasaPercent / 100), 0);

        return new self(RoundingEngine::roundLegacy($rounded));
    }

    public function isZero(): bool
    {
        return $this->pesos <= 0;
    }
}
