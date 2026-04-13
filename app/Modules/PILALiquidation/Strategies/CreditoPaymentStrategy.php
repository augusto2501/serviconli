<?php

namespace App\Modules\PILALiquidation\Strategies;

use App\Modules\Billing\Models\AccountReceivable;
use App\Modules\Billing\Models\BillInvoice;
use App\Modules\Billing\Services\ConsecutiveService;
use App\Modules\PILALiquidation\Models\PilaLiquidation;

/**
 * RN-12 — Crédito: genera saldo pendiente CxC en cartera.
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
        $consecutive = app(ConsecutiveService::class);

        $invoice = BillInvoice::query()->create([
            'public_number' => $consecutive->next('RC'),
            'affiliate_id' => $liquidation->affiliate_id,
            'payer_id' => null,
            'fecha' => now(),
            'tipo' => 'APORTE_INDIVIDUAL',
            'payment_method' => 'CREDITO',
            'total_pesos' => $liquidation->total_social_security_pesos,
            'estado' => 'CARTERA',
        ]);

        // RF-087: crear cuenta por cobrar para seguimiento en cartera
        AccountReceivable::query()->create([
            'affiliate_id' => $liquidation->affiliate_id,
            'invoice_id' => $invoice->id,
            'concept' => 'APORTE_INDIVIDUAL',
            'amount_pesos' => $liquidation->total_social_security_pesos,
            'balance_pesos' => $liquidation->total_social_security_pesos,
            'due_date' => now()->addDays(30),
            'status' => 'PENDIENTE',
        ]);

        return [
            'receipt_id' => $invoice->public_number,
            'message' => 'Aporte registrado a crédito. Se genera saldo en cartera.',
            'extra' => ['invoice_id' => $invoice->id],
        ];
    }
}
