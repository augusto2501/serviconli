<?php

namespace App\Modules\RegulatoryEngine\ValueObjects;

use App\Modules\RegulatoryEngine\Services\RoundingEngine;

/**
 * Ingreso Base de Cotización en pesos COP (entero).
 *
 * Portado de Access Form_005 línea 11262.
 *
 * @see DOCUMENTO_RECTOR §2.3, RN-01
 */
final readonly class IBC
{
    public function __construct(public int $valueInPesos) {}

    /**
     * Calcula IBC desde salario y días, redondeando al millar superior.
     * Fórmula Rector: IBC = roundIBC(Int((Salario/30) × Días))
     */
    public static function calcular(int $salarioPesos, int $dias): self
    {
        if ($salarioPesos <= 0 || $dias <= 0) {
            return new self(0);
        }
        $raw = intval(($salarioPesos / 30) * $dias);

        return new self(RoundingEngine::roundIBC($raw));
    }

    public static function fromRaw(int $rawPesos): self
    {
        return new self($rawPesos);
    }

    public function roundToMillarSuperior(): self
    {
        return new self(RoundingEngine::roundIBC($this->valueInPesos));
    }

    public function isZero(): bool
    {
        return $this->valueInPesos <= 0;
    }
}
