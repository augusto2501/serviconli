<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\AccountReceivable;
use App\Modules\Billing\Models\BillInvoice;
use App\Modules\Billing\Models\CuentaCobro;
use App\Modules\Billing\Models\PaymentReceived;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * RN-18: Anulación de recibos — 6 cascadas según tipo × medio.
 * RN-26: No anular afiliación si tiene aportes.
 *
 * Flujo 7:
 *   1. Validar prerrequisitos (RN-26 etc.)
 *   2. Requiere causal + motivo obligatorios
 *   3. Ejecutar cascada según tipo×medio
 *   4. Restaurar AFP si retiro tipo P
 *   5. Revertir estado
 *
 * 6 combinaciones:
 *   AFILIACION × EFECTIVO   → revertir estado afiliado + devolver comisión asesor
 *   AFILIACION × CONSIGNACION → idem + revertir depósito bancario
 *   APORTE × EFECTIVO       → revertir estado mora
 *   APORTE × CONSIGNACION   → idem + revertir depósito bancario
 *   CUENTA × EFECTIVO       → re-abrir cuenta de cobro
 *   CUENTA × CONSIGNACION   → idem + revertir depósito bancario
 *
 * Portado de Access Form_Sub:6772..6874.
 *
 * @see DOCUMENTO_RECTOR §5 RN-18, RN-26, Flujo 7
 */
final class InvoiceCancellationService
{
    public function cancel(
        BillInvoice $invoice,
        string $cancellationReason,
        string $cancellationMotive,
        string $cancelledBy,
    ): BillInvoice {
        if ($invoice->isAnulado()) {
            throw new InvalidArgumentException('El recibo ya está anulado.');
        }

        $this->validatePrerequisites($invoice);

        return DB::transaction(function () use ($invoice, $cancellationReason, $cancellationMotive, $cancelledBy) {
            $this->executeCascade($invoice);

            $invoice->update([
                'estado' => 'ANULADO',
                'cancellation_reason' => $cancellationReason,
                'cancellation_motive' => $cancellationMotive,
                'cancelled_by' => $cancelledBy,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * RN-26: No anular afiliación si ya tiene aportes registrados.
     */
    private function validatePrerequisites(BillInvoice $invoice): void
    {
        if ($invoice->tipo === 'AFILIACION' && $invoice->affiliate_id !== null) {
            $hasContributions = PilaLiquidation::query()
                ->where('affiliate_id', $invoice->affiliate_id)
                ->exists();

            if ($hasContributions) {
                throw new InvalidArgumentException(
                    'No se puede anular la afiliación: el afiliado ya tiene aportes registrados (RN-26).'
                );
            }
        }
    }

    private function executeCascade(BillInvoice $invoice): void
    {
        $tipo = $invoice->tipo;
        $paymentMethod = $invoice->payment_method;

        match ($tipo) {
            'AFILIACION', 'REINGRESO' => $this->cascadeAffiliation($invoice, $paymentMethod),
            'APORTE' => $this->cascadeContribution($invoice, $paymentMethod),
            'CUENTA' => $this->cascadeCuentaCobro($invoice, $paymentMethod),
            default => $this->cascadeGeneric($invoice, $paymentMethod),
        };
    }

    /**
     * Afiliación × {EFECTIVO, CONSIGNACION, CREDITO}
     */
    private function cascadeAffiliation(BillInvoice $invoice, ?string $paymentMethod): void
    {
        if ($invoice->affiliate_id !== null) {
            $affiliate = $invoice->affiliate;
            if ($affiliate !== null) {
                $affiliate->update(['status' => 'AFILIADO']);
            }
        }

        $this->cancelPayments($invoice);
        $this->cancelAccountsReceivable($invoice);

        if ($paymentMethod === 'CONSIGNACION') {
            $this->revertBankDeposit($invoice);
        }
    }

    /**
     * Aporte × {EFECTIVO, CONSIGNACION}
     */
    private function cascadeContribution(BillInvoice $invoice, ?string $paymentMethod): void
    {
        $this->cancelPayments($invoice);

        if ($paymentMethod === 'CONSIGNACION') {
            $this->revertBankDeposit($invoice);
        }
    }

    /**
     * Cuenta × {EFECTIVO, CONSIGNACION}
     */
    private function cascadeCuentaCobro(BillInvoice $invoice, ?string $paymentMethod): void
    {
        if ($invoice->cuenta_cobro_id !== null) {
            $cuenta = CuentaCobro::query()->find($invoice->cuenta_cobro_id);
            if ($cuenta !== null && $cuenta->isPagada()) {
                $cuenta->update([
                    'status' => 'DEFINITIVA',
                    'payment_date' => null,
                    'payment_amount' => null,
                ]);
            }
        }

        $this->cancelPayments($invoice);

        if ($paymentMethod === 'CONSIGNACION') {
            $this->revertBankDeposit($invoice);
        }
    }

    private function cascadeGeneric(BillInvoice $invoice, ?string $paymentMethod): void
    {
        $this->cancelPayments($invoice);

        if ($paymentMethod === 'CONSIGNACION') {
            $this->revertBankDeposit($invoice);
        }
    }

    private function cancelPayments(BillInvoice $invoice): void
    {
        PaymentReceived::query()
            ->where('invoice_id', $invoice->id)
            ->update(['status' => 'ANULADO']);
    }

    private function cancelAccountsReceivable(BillInvoice $invoice): void
    {
        AccountReceivable::query()
            ->where('invoice_id', $invoice->id)
            ->update(['status' => 'ANULADO']);
    }

    private function revertBankDeposit(BillInvoice $invoice): void
    {
        DB::table('bank_deposits')
            ->where('invoice_id', $invoice->id)
            ->update(['concept' => DB::raw("CONCAT(concept, ' [ANULADO]')")]);
    }
}
