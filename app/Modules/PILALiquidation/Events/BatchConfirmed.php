<?php

namespace App\Modules\PILALiquidation\Events;

use App\Modules\PILALiquidation\Models\LiquidationBatch;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitido cuando un lote de liquidación pasa de BORRADOR a LIQUIDADO.
 *
 * Listeners: GenerarCuentaCobro, NotificarPagador.
 *
 * @see DOCUMENTO_RECTOR §6 Flujo 4, RN-08
 */
final class BatchConfirmed
{
    use Dispatchable;

    public function __construct(
        public readonly LiquidationBatch $batch,
    ) {}
}
