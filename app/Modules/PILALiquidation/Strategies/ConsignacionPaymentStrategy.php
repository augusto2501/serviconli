<?php

namespace App\Modules\PILALiquidation\Strategies;

use App\Modules\Billing\Models\BillInvoice;
use App\Modules\Billing\Models\PaymentReceived;
use App\Modules\Billing\Services\ConsecutiveService;
use App\Modules\Billing\Services\PaymentValidationService;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\ThirdParties\Models\BankDeposit;

/**
 * RN-12 — Consignación: registra depósito bancario + pendiente de cruce.
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
        $consecutive = app(ConsecutiveService::class);
        $bankReference = $context['bank_reference'] ?? null;
        $bankName = $context['bank_name'] ?? 'N/A';
        $depositType = $context['deposit_type'] ?? 'LOCAL';
        $amount = $context['bank_amount'] ?? $liquidation->total_social_security_pesos;

        // RF-075: validación referencia duplicada (warning, no bloqueo)
        $duplicateWarning = null;
        if ($bankReference !== null) {
            $validator = app(PaymentValidationService::class);
            $duplicateWarning = $validator->checkDuplicateReference($bankReference);
        }

        $invoice = BillInvoice::query()->create([
            'public_number' => $consecutive->next('RC'),
            'affiliate_id' => $liquidation->affiliate_id,
            'payer_id' => null,
            'fecha' => now(),
            'tipo' => 'APORTE_INDIVIDUAL',
            'payment_method' => 'CONSIGNACION',
            'total_pesos' => $amount,
            'estado' => 'PENDIENTE_CRUCE',
        ]);

        // RF-075: registrar depósito bancario en tp_bank_deposits
        $deposit = BankDeposit::query()->create([
            'invoice_id' => $invoice->id,
            'affiliate_id' => $liquidation->affiliate_id,
            'bank_name' => $bankName,
            'reference' => $bankReference ?? '',
            'amount_pesos' => $amount,
            'deposit_type' => $depositType,
            'expected_amount_pesos' => $liquidation->total_social_security_pesos,
            'concept' => 'APORTE_INDIVIDUAL',
            'status' => 'ACTIVO',
            'created_by' => $context['created_by_id'] ?? null,
        ]);

        PaymentReceived::query()->create([
            'invoice_id' => $invoice->id,
            'affiliate_id' => $liquidation->affiliate_id,
            'payment_method' => 'CONSIGNACION',
            'amount_pesos' => $amount,
            'payment_date' => now(),
            'bank_name' => $bankName,
            'bank_reference' => $bankReference,
            'status' => 'PENDIENTE',
            'received_by' => $context['received_by'] ?? 'system',
        ]);

        $extra = [
            'invoice_id' => $invoice->id,
            'deposit_id' => $deposit->id,
            'bank_name' => $bankName,
            'bank_reference' => $bankReference,
        ];

        if ($duplicateWarning) {
            $extra['duplicateReferenceWarning'] = $duplicateWarning;
        }

        $surplus = $amount - $liquidation->total_social_security_pesos;
        if ($surplus > 0) {
            $extra['surplusPesos'] = $surplus;
        }

        return [
            'receipt_id' => $invoice->public_number,
            'message' => 'Consignación registrada. Pendiente de cruce bancario.',
            'extra' => $extra,
        ];
    }
}
