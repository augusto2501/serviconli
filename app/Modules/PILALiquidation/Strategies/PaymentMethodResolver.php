<?php

namespace App\Modules\PILALiquidation\Strategies;

use InvalidArgumentException;

/**
 * Resuelve el PaymentMethodStrategy correcto según el código de medio de pago.
 *
 * @see DOCUMENTO_RECTOR §5.5, RN-12
 */
final class PaymentMethodResolver
{
    /** @var array<string, PaymentMethodStrategy> */
    private readonly array $strategies;

    public function __construct()
    {
        $pool = [
            new EfectivoPaymentStrategy,
            new ConsignacionPaymentStrategy,
            new CreditoPaymentStrategy,
            new CuentaCobroPaymentStrategy,
        ];

        $map = [];
        foreach ($pool as $strategy) {
            $map[$strategy->code()] = $strategy;
        }
        $this->strategies = $map;
    }

    public function resolve(string $code): PaymentMethodStrategy
    {
        return $this->strategies[$code]
            ?? throw new InvalidArgumentException("Medio de pago desconocido: {$code}");
    }

    /** @return list<array{code: string, label: string}> */
    public function available(): array
    {
        return array_map(
            static fn (PaymentMethodStrategy $s) => ['code' => $s->code(), 'label' => $s->label()],
            array_values($this->strategies),
        );
    }
}
