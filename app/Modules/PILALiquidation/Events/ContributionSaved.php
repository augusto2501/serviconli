<?php

namespace App\Modules\PILALiquidation\Events;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitido al guardar un aporte individual — Flujo 3 post-guardado.
 *
 * Listeners: actualizar estado mora, procesar novedades, generar recibo.
 *
 * @see DOCUMENTO_RECTOR §6 Flujo 3, §2.4
 */
final class ContributionSaved
{
    use Dispatchable;

    public function __construct(
        public readonly Affiliate $affiliate,
        public readonly PilaLiquidation $liquidation,
        public readonly string $paymentMethod,
        public readonly array $novelties = [],
        public readonly array $bankData = [],
    ) {}
}
