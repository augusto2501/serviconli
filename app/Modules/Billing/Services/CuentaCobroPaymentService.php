<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\BillInvoice;
use App\Modules\Billing\Models\CuentaCobro;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Billing\Models\PaymentReceived;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * RN-17: Pago de cuenta de cobro.
 *
 * Flujo 6:
 *   Oportuno (antes de payment_date_1) → paga total_1 (sin intereses)
 *   Con mora  (después de payment_date_1) → paga total_2 (con intereses)
 *   NO se aceptan pagos parciales.
 *
 * Al pagar:
 *   1. Valida que el monto sea exacto (Tot1 o Tot2)
 *   2. Genera recibo (BillInvoice) de tipo CUENTA con 8 líneas conceptuales
 *   3. Registra pago recibido
 *   4. Marca cuenta como PAGADA
 *   5. Los aportes ya pasan a planilla (desde el lote confirmado)
 *
 * Portado de Access Form_Sub:18583.
 *
 * @see DOCUMENTO_RECTOR §5 RN-17, Flujo 6
 */
final class CuentaCobroPaymentService
{
    public function __construct(
        private readonly ConsecutiveService $consecutiveService,
        private readonly PaymentValidationService $paymentValidationService,
    ) {}

    public function pay(
        CuentaCobro $cuenta,
        string $paymentMethod,
        int $amountPesos,
        ?string $bankName = null,
        ?string $bankReference = null,
        ?string $receivedBy = null,
    ): BillInvoice {
        $this->validatePayable($cuenta);

        $isOportuno = $this->isOportuno($cuenta);
        $expectedAmount = $isOportuno ? $cuenta->total_1 : $cuenta->total_2;

        if ($amountPesos !== $expectedAmount) {
            throw new InvalidArgumentException(
                "El pago debe ser exacto: \${$expectedAmount} (" . ($isOportuno ? 'oportuno' : 'con mora') . '). '
                . "Recibido: \${$amountPesos}. No se aceptan pagos parciales."
            );
        }

        if ($paymentMethod === 'CONSIGNACION' && $bankReference !== null) {
            $this->paymentValidationService->validateNoDuplicateDeposit($bankReference, $bankName);
        }

        return DB::transaction(function () use (
            $cuenta, $paymentMethod, $amountPesos, $bankName, $bankReference, $receivedBy, $isOportuno,
        ) {
            $invoice = BillInvoice::query()->create([
                'public_number' => $this->consecutiveService->next('RC'),
                'fecha' => now()->toDateString(),
                'payer_id' => $cuenta->payer_id,
                'cuenta_cobro_id' => $cuenta->id,
                'tipo' => 'CUENTA',
                'payment_method' => $paymentMethod,
                'total_pesos' => $amountPesos,
                'estado' => 'ACTIVO',
            ]);

            $this->createInvoiceLines($invoice, $cuenta, $isOportuno);

            PaymentReceived::query()->create([
                'invoice_id' => $invoice->id,
                'cuenta_cobro_id' => $cuenta->id,
                'payer_id' => $cuenta->payer_id,
                'payment_method' => $paymentMethod,
                'amount_pesos' => $amountPesos,
                'payment_date' => now()->toDateString(),
                'bank_name' => $bankName,
                'bank_reference' => $bankReference,
                'status' => 'APLICADO',
                'received_by' => $receivedBy,
            ]);

            $cuenta->update([
                'status' => 'PAGADA',
                'payment_date' => now()->toDateString(),
                'payment_amount' => $amountPesos,
            ]);

            return $invoice->load('items');
        });
    }

    /**
     * Determina si la cuenta se paga en plazo oportuno (Tot1).
     */
    public function isOportuno(CuentaCobro $cuenta): bool
    {
        if ($cuenta->payment_date_1 === null) {
            return true;
        }

        return now()->lte($cuenta->payment_date_1);
    }

    /**
     * 8 líneas de recibo según Documento Rector Flujo 6.
     */
    private function createInvoiceLines(BillInvoice $invoice, CuentaCobro $cuenta, bool $isOportuno): void
    {
        $concepts = [
            ['Aportes EPS / Salud', $cuenta->total_eps],
            ['Aportes AFP / Pensión', $cuenta->total_afp],
            ['Aportes ARL', $cuenta->total_arl],
            ['Aportes CCF', $cuenta->total_ccf],
            ['Administración', $cuenta->total_admin],
            ['Afiliación', $cuenta->total_affiliation],
            ['Subtotal SS', $cuenta->total_eps + $cuenta->total_afp + $cuenta->total_arl + $cuenta->total_ccf],
            ['Intereses mora', $isOportuno ? 0 : $cuenta->interest_mora],
        ];

        foreach ($concepts as $i => [$concept, $amount]) {
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'line_number' => $i + 1,
                'concept' => $concept,
                'amount_pesos' => (int) $amount,
            ]);
        }
    }

    private function validatePayable(CuentaCobro $cuenta): void
    {
        if ($cuenta->isPagada()) {
            throw new InvalidArgumentException('Esta cuenta de cobro ya fue pagada.');
        }

        if ($cuenta->isAnulada()) {
            throw new InvalidArgumentException('No se puede pagar una cuenta anulada.');
        }

        if ($cuenta->isPreCuenta()) {
            throw new InvalidArgumentException(
                'No se puede pagar una pre-cuenta. Debe convertirse en definitiva primero.'
            );
        }
    }
}
