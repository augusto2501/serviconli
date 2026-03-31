<?php

namespace App\Modules\PILALiquidation\Strategies;

use App\Modules\PILALiquidation\Models\PilaLiquidation;

/**
 * Contrato para flujos post-guardado según medio de pago — RN-12.
 *
 * Efectivo     → caja: recibo caja + marcar como pagado.
 * Consignación → bancos: pendiente de cruce.
 * Crédito      → cartera: genera saldo.
 * Cuenta cobro → patronal: genera factura pendiente.
 *
 * @see DOCUMENTO_RECTOR §5.5, §5.3
 */
interface PaymentMethodStrategy
{
    public function code(): string;

    public function label(): string;

    /**
     * Ejecuta el flujo post-guardado.
     *
     * @return array{receipt_id: string|null, message: string, extra: array<string, mixed>}
     */
    public function process(PilaLiquidation $liquidation, array $context = []): array;
}
