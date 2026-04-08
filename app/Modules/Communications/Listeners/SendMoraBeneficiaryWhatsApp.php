<?php

namespace App\Modules\Communications\Listeners;

use App\Modules\Affiliates\Events\MoraBeneficiaryAlertNeeded;
use App\Modules\Communications\Services\WhatsAppOutboundService;

final class SendMoraBeneficiaryWhatsApp
{
    public function __construct(
        private readonly WhatsAppOutboundService $whatsApp,
    ) {}

    public function handle(MoraBeneficiaryAlertNeeded $event): void
    {
        $affiliate = $event->affiliate;

        $this->whatsApp->sendTemplate($affiliate, 'mora_beneficiary_alert', [
            'name' => (string) ($affiliate->person?->first_name ?? 'afiliado'),
            'ref' => (string) $affiliate->id,
        ]);
    }
}
