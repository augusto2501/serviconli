<?php

namespace App\Modules\PILALiquidation\Listeners;

use App\Modules\Affiliates\Services\AffiliateStatusMachine;
use App\Modules\PILALiquidation\Events\ContributionSaved;

/**
 * RF-073: Al registrarse un pago → deescalar un nivel de mora.
 * RF-071: Primer pago → activar afiliado.
 *
 * @see DOCUMENTO_RECTOR §5.4, RN-05
 */
final class UpdateMoraStatusOnPayment
{
    public function __construct(
        private readonly AffiliateStatusMachine $statusMachine,
    ) {}

    public function handle(ContributionSaved $event): void
    {
        $affiliate = $event->affiliate;
        $currentCode = $this->statusMachine->currentStatusCode($affiliate);

        if ($currentCode === 'AFILIADO') {
            $this->statusMachine->activateOnFirstPayment($affiliate);

            return;
        }

        if ($this->statusMachine->isInMora($affiliate)) {
            $this->statusMachine->deescalate($affiliate);
        }
    }
}
