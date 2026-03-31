<?php

namespace App\Modules\PILALiquidation\Listeners;

use App\Modules\Billing\Models\BillInvoice;
use App\Modules\PILALiquidation\Events\BatchConfirmed;
use Illuminate\Support\Str;

/**
 * RN-08: Al confirmar un lote de liquidación por empresa,
 * si el pagador paga por cuenta de cobro → generar factura automática.
 *
 * Portado de Access Form_004 Btn_Confirmar → CuentaCobro.
 *
 * @see DOCUMENTO_RECTOR §5.5, RN-08, RF-069
 */
final class GenerateCuentaCobroOnBatchConfirm
{
    public function handle(BatchConfirmed $event): void
    {
        $batch = $event->batch;
        $batch->loadMissing('payer');

        $payer = $batch->payer;
        if ($payer === null) {
            return;
        }

        $invoice = BillInvoice::query()->create([
            'public_number' => 'CC-LOTE-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
            'affiliate_id' => null,
            'payer_id' => $payer->id,
            'tipo' => 'LIQUIDACION_LOTE',
            'payment_method' => 'CUENTA_COBRO',
            'total_pesos' => $batch->grand_total,
            'estado' => 'PENDIENTE_COBRO',
        ]);

        $batch->update([
            'planilla_number' => $invoice->public_number,
        ]);
    }
}
