<?php

namespace App\Modules\PILALiquidation\Strategies;

use App\Modules\Billing\Models\BillInvoice;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use Illuminate\Support\Str;

/**
 * RN-12 — Crédito: genera saldo pendiente en cartera del afiliado.
 *
 * @see DOCUMENTO_RECTOR §5.5
 */
final class CreditoPaymentStrategy implements PaymentMethodStrategy
{
    public function code(): string
    {
        return 'CREDITO';
    }

    public function label(): string
    {
        return 'Crédito (cartera)';
    }

    public function process(PilaLiquidation $liquidation, array $context = []): array
    {
        $invoice = BillInvoice::query()->create([
            'public_number' => 'CR-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'affiliate_id' => $liquidation->affiliate_id,
            'payer_id' => null,
            'tipo' => 'APORTE_INDIVIDUAL',
            'payment_method' => 'CREDITO',
            'total_pesos' => $liquidation->total_social_security_pesos,
            'estado' => 'CARTERA',
        ]);

        return [
            'receipt_id' => $invoice->public_number,
            'message' => 'Aporte registrado a crédito. Se genera saldo en cartera.',
            'extra' => ['invoice_id' => $invoice->id],
        ];
    }
}
