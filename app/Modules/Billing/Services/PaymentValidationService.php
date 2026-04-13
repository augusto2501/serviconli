<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\PaymentReceived;
use InvalidArgumentException;

/**
 * RN-24: Validación de consignación duplicada.
 *
 * Si se detecta una referencia bancaria ya registrada:
 *   - Warning + marcar como excedente si corresponde
 *   - Error si referencia idéntica y banco idéntico
 *
 * Portado de Access Form_Sub:18418.
 *
 * @see DOCUMENTO_RECTOR §5 RN-24
 */
final class PaymentValidationService
{
    /**
     * Valida que no exista una consignación con la misma referencia bancaria.
     *
     * @throws InvalidArgumentException si es duplicada exacta
     */
    public function validateNoDuplicateDeposit(string $bankReference, ?string $bankName = null): void
    {
        $query = PaymentReceived::query()
            ->where('payment_method', 'CONSIGNACION')
            ->where('bank_reference', $bankReference)
            ->where('status', '!=', 'ANULADO');

        if ($bankName !== null) {
            $query->where('bank_name', $bankName);
        }

        $existing = $query->first();

        if ($existing !== null) {
            $date = $existing->payment_date?->format('Y-m-d') ?? 'N/A';
            throw new InvalidArgumentException(
                "Consignación duplicada: la referencia '{$bankReference}' ya fue registrada "
                ."el {$date} por \${$existing->amount_pesos}. "
                .'Verifique si se trata de un excedente o un error de digitación (RN-24).'
            );
        }
    }

    /** RF-075: verificación no-bloqueante de referencia duplicada (warning) */
    public function checkDuplicateReference(string $bankReference): ?string
    {
        $existing = PaymentReceived::query()
            ->where('payment_method', 'CONSIGNACION')
            ->where('bank_reference', $bankReference)
            ->where('status', '!=', 'ANULADO')
            ->first();

        if ($existing !== null) {
            $date = $existing->payment_date?->format('Y-m-d') ?? 'N/A';

            return "Referencia '{$bankReference}' ya registrada el {$date} por \${$existing->amount_pesos}.";
        }

        return null;
    }

    /**
     * Detecta posible excedente (misma referencia, diferente monto).
     */
    public function detectExcess(string $bankReference, int $amountPesos): ?array
    {
        $existing = PaymentReceived::query()
            ->where('payment_method', 'CONSIGNACION')
            ->where('bank_reference', $bankReference)
            ->where('status', '!=', 'ANULADO')
            ->first();

        if ($existing === null) {
            return null;
        }

        $diff = $amountPesos - (int) $existing->amount_pesos;
        if ($diff > 0) {
            return [
                'original_payment_id' => $existing->id,
                'original_amount' => (int) $existing->amount_pesos,
                'new_amount' => $amountPesos,
                'excess' => $diff,
            ];
        }

        return null;
    }
}
