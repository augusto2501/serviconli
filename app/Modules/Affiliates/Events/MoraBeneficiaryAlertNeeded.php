<?php

namespace App\Modules\Affiliates\Events;

use App\Modules\Affiliates\Models\Affiliate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * RF-074 / D.780 — mora &gt; 1 mes (nivel alerta beneficiarios): notificación al afiliado.
 */
final class MoraBeneficiaryAlertNeeded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Affiliate $affiliate,
    ) {}
}
