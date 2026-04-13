<?php

namespace App\Modules\PILALiquidation\Strategies;

use App\Modules\Billing\Models\BillInvoice;
use App\Modules\Billing\Models\PaymentReceived;
use App\Modules\Billing\Services\ConsecutiveService;
use App\Modules\PILALiquidation\Models\PilaLiquidation;

/**
 * RN-12 — Efectivo: pago inmediato en caja, genera recibo + PaymentReceived.
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
        $consecutive = app(ConsecutiveService::class);

        $invoice = BillInvoice::query()->create([
            'public_number' => $consecutive->next('RC'),
            'affiliate_id' => $liquidation->affiliate_id,
            'payer_id' => null,
            'fecha' => now(),
            'tipo' => 'APORTE_INDIVIDUAL',
            'payment_method' => 'EFECTIVO',
            'total_pesos' => $liquidation->total_social_security_pesos,
            'estado' => 'PAGADO',
        ]);

        // RF-087: registrar pago recibido para trazabilidad en cuadre de caja
        PaymentReceived::query()->create([
            'invoice_id' => $invoice->id,
            'affiliate_id' => $liquidation->affiliate_id,
            'payment_method' => 'EFECTIVO',
            'amount_pesos' => $liquidation->total_social_security_pesos,
            'payment_date' => now(),
            'status' => 'ACTIVO',
            'received_by' => $context['received_by'] ?? 'system',
        ]);

        return [
            'receipt_id' => $invoice->public_number,
            'message' => 'Pago en efectivo registrado. Recibo de caja generado.',
            'extra' => ['invoice_id' => $invoice->id],
        ];
    }
}
