<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\BillInvoice;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Billing\Models\PaymentReceived;
use Illuminate\Support\Facades\DB;

/**
 * Genera recibos de caja con consecutivo RC-{YYYY}-{NNNN}.
 *
 * Cada recibo:
 *   - Número público secuencial por año (RC-2026-0001, RC-2026-0002, ...)
 *   - Fecha de expedición
 *   - Tipo (AFILIACION, APORTE, REINGRESO, CUENTA, CAJA_GENERAL)
 *   - Medio de pago (EFECTIVO, CONSIGNACION, CREDITO, CUENTA_COBRO)
 *   - Líneas de detalle (conceptos)
 *   - Total en pesos y en letras (español colombiano)
 *
 * @see DOCUMENTO_RECTOR §5 RN-12, Flujo 3/6/10
 */
final class ReciboCajaService
{
    public function __construct(
        private readonly ConsecutiveService $consecutiveService,
        private readonly NumberToWordsService $numberToWordsService,
    ) {}

    /**
     * Crea un recibo de caja.
     *
     * @param  array<array{concept: string, amount_pesos: int}>  $items
     */
    public function createReceipt(
        string $tipo,
        string $paymentMethod,
        array $items,
        ?int $affiliateId = null,
        ?int $payerId = null,
        ?string $bankName = null,
        ?string $bankReference = null,
        ?string $receivedBy = null,
    ): BillInvoice {
        $totalPesos = array_sum(array_column($items, 'amount_pesos'));

        return DB::transaction(function () use (
            $tipo, $paymentMethod, $items, $affiliateId, $payerId,
            $totalPesos, $bankName, $bankReference, $receivedBy,
        ) {
            $invoice = BillInvoice::query()->create([
                'public_number' => $this->consecutiveService->next('RC'),
                'fecha' => now()->toDateString(),
                'affiliate_id' => $affiliateId,
                'payer_id' => $payerId,
                'tipo' => $tipo,
                'payment_method' => $paymentMethod,
                'total_pesos' => $totalPesos,
                'estado' => 'ACTIVO',
            ]);

            foreach ($items as $i => $item) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'line_number' => $i + 1,
                    'concept' => $item['concept'],
                    'amount_pesos' => $item['amount_pesos'],
                ]);
            }

            if ($paymentMethod !== 'CREDITO') {
                PaymentReceived::query()->create([
                    'invoice_id' => $invoice->id,
                    'affiliate_id' => $affiliateId,
                    'payer_id' => $payerId,
                    'payment_method' => $paymentMethod,
                    'amount_pesos' => $totalPesos,
                    'payment_date' => now()->toDateString(),
                    'bank_name' => $bankName,
                    'bank_reference' => $bankReference,
                    'status' => 'APLICADO',
                    'received_by' => $receivedBy,
                ]);
            }

            return $invoice->load('items');
        });
    }

    /**
     * Obtiene el total en letras para un recibo.
     */
    public function totalInWords(int $totalPesos): string
    {
        return $this->numberToWordsService->convert($totalPesos);
    }
}
