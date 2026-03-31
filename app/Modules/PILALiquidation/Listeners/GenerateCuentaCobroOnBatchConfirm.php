<?php

namespace App\Modules\PILALiquidation\Listeners;

use App\Modules\Billing\Models\ServiceContract;
use App\Modules\Billing\Services\CuentaCobroService;
use App\Modules\PILALiquidation\Events\BatchConfirmed;

/**
 * RN-08: Al confirmar un lote de liquidación por empresa,
 * si el pagador paga por cuenta de cobro → generar cuenta de cobro automática.
 *
 * Portado de Access Form_004 Btn_Confirmar → CuentaCobro.
 *
 * @see DOCUMENTO_RECTOR §5.5, RN-08, RF-069
 */
final class GenerateCuentaCobroOnBatchConfirm
{
    public function __construct(
        private readonly CuentaCobroService $cuentaCobroService,
    ) {}

    public function handle(BatchConfirmed $event): void
    {
        $batch = $event->batch;
        $batch->loadMissing('payer');

        $payer = $batch->payer;
        if ($payer === null) {
            return;
        }

        $generatesCC = $payer->generates_cuenta_cobro;
        if (! $generatesCC) {
            $contract = ServiceContract::query()
                ->where('payer_id', $payer->id)
                ->where('status', 'ACTIVO')
                ->latest('vigencia_start')
                ->first();

            $generatesCC = $contract?->generates_cuenta_cobro ?? false;
        }

        if (! $generatesCC) {
            return;
        }

        $cuenta = $this->cuentaCobroService->generatePreCuenta(
            payerId: $payer->id,
            periodYear: $batch->period_year,
            periodMonth: $batch->period_month,
            mode: 'PLENO',
            batchId: $batch->id,
        );

        $batch->update([
            'planilla_number' => $cuenta->cuenta_number,
        ]);
    }
}
