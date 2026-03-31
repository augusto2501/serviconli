<?php

namespace App\Modules\PILALiquidation\Events;

use App\Modules\Affiliates\Models\Affiliate;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitido cuando se confirma un pago.
 *
 * Listeners: UpdateCartera, UpdateMoraStatus, ReconcileCash.
 *
 * @see DOCUMENTO_RECTOR §2.4
 */
final class PaymentReceived
{
    use Dispatchable;

    public function __construct(
        public readonly Affiliate $affiliate,
        public readonly int $totalPaidPesos,
        public readonly string $paymentMethod,
        public readonly string $periodKey,
    ) {}
}
