<?php

namespace App\Modules\PILALiquidation\Strategies;

use App\Modules\Billing\Models\BillInvoice;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use Illuminate\Support\Str;

/**
 * RN-12 — Consignación: pendiente de cruce bancario.
 *
 * @see DOCUMENTO_RECTOR §5.5
 */
final class ConsignacionPaymentStrategy implements PaymentMethodStrategy
{
    public function code(): string
    {
        return 'CONSIGNACION';
    }

    public function label(): string
    {
        return 'Consignación bancaria';
    }

    public function process(PilaLiquidation $liquidation, array $context = []): array
    {
        $invoice = BillInvoice::query()->create([
            'public_number' => 'CB-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'affiliate_id' => $liquidation->affiliate_id,
            'payer_id' => null,
            'tipo' => 'APORTE_INDIVIDUAL',
            'payment_method' => 'CONSIGNACION',
            'total_pesos' => $context['bank_amount'] ?? $liquidation->total_social_security_pesos,
            'estado' => 'PENDIENTE_CRUCE',
        ]);

        return [
            'receipt_id' => $invoice->public_number,
            'message' => 'Consignación registrada. Pendiente de cruce bancario.',
            'extra' => [
                'invoice_id' => $invoice->id,
                'bank_name' => $context['bank_name'] ?? null,
                'bank_reference' => $context['bank_reference'] ?? null,
            ],
        ];
    }
}
