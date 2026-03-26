<?php

namespace App\Modules\RegulatoryEngine\ValueObjects;

/** Monto de aporte en pesos COP (entero). */
final readonly class MontoAporte
{
    public function __construct(public int $pesos) {}

    public static function fromInt(int $pesos): self
    {
        return new self(max(0, $pesos));
    }
}
