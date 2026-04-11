<?php

namespace App\Modules\PILALiquidation\Strategies;

use App\Modules\Billing\Models\BillInvoice;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use Illuminate\Support\Str;

/**
 * RN-12 — Efectivo: pago inmediato en caja, genera recibo.
 *
 * @see DOCUMENTO_RECTOR §5.5
 */
final class EfectivoPaymentStrategy implements PaymentMethodStrategy
{
    public function code(): string
    {
        return 'EFECTIVO';
    }

    public function label(): string
    {
        return 'Efectivo en caja';
    }

    public function process(PilaLiquidation $liquidation, array $context = []): array
    {
        $invoice = BillInvoice::query()->create([
            'public_number' => 'RC-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'affiliate_id' => $liquidation->affiliate_id,
            'payer_id' => null,
            'tipo' => 'APORTE_INDIVIDUAL',
            'payment_method' => 'EFECTIVO',
            'total_pesos' => $liquidation->total_social_security_pesos,
            'estado' => 'PAGADO',
        ]);

        return [
            'receipt_id' => $invoice->public_number,
            'message' => 'Pago en efectivo registrado. Recibo de caja generado.',
            'extra' => ['invoice_id' => $invoice->id],
        ];
    }
}
