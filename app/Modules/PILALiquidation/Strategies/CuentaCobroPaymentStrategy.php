<?php

namespace App\Modules\PILALiquidation\Strategies;

use App\Modules\Billing\Models\BillInvoice;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use Illuminate\Support\Str;

/**
 * RN-12 — Cuenta cobro: factura pendiente al pagador/empleador.
 *
 * @see DOCUMENTO_RECTOR §5.5
 */
final class CuentaCobroPaymentStrategy implements PaymentMethodStrategy
{
    public function code(): string
    {
        return 'CUENTA_COBRO';
    }

    public function label(): string
    {
        return 'Cuenta de cobro (patronal)';
    }

    public function process(PilaLiquidation $liquidation, array $context = []): array
    {
        $payerId = $context['payer_id'] ?? null;

        $invoice = BillInvoice::query()->create([
            'public_number' => 'CC-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
            'affiliate_id' => $liquidation->affiliate_id,
            'payer_id' => $payerId,
            'tipo' => 'APORTE_INDIVIDUAL',
            'payment_method' => 'CUENTA_COBRO',
            'total_pesos' => $liquidation->total_social_security_pesos,
            'estado' => 'PENDIENTE_COBRO',
        ]);

        return [
            'receipt_id' => $invoice->public_number,
            'message' => 'Cuenta de cobro generada. Pendiente de pago patronal.',
            'extra' => [
                'invoice_id' => $invoice->id,
                'payer_id' => $payerId,
            ],
        ];
    }
}
