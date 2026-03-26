<?php

namespace App\Modules\RegulatoryEngine\ValueObjects;

/**
 * Ingreso Base de Cotización en pesos COP (entero).
 *
 * @see Documento rector RN-01 — IBC al millar superior
 */
final readonly class IBC
{
    public function __construct(public int $valueInPesos) {}

    public static function fromRaw(int $rawPesos): self
    {
        return new self($rawPesos);
    }

    public function roundToMillarSuperior(): self
    {
        if ($this->valueInPesos <= 0) {
            return new self(0);
        }

        return new self((int) ceil($this->valueInPesos / 1000) * 1000);
    }
}
