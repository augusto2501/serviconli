<?php

namespace App\Modules\Affiliates\Listeners;

use App\Modules\Affiliates\Events\ARLRetirementReminderRequested;
use Illuminate\Support\Facades\Log;

final class LogARLRetirementReminder
{
    public function handle(ARLRetirementReminderRequested $event): void
    {
        $n = $event->novelty;
        Log::warning('ARL: completar retiro del afiliado en la plataforma de la ARL (RN-28).', [
            'affiliate_id' => $n->affiliate_id,
            'novelty_id' => $n->id,
            'retirement_scope' => $n->retirement_scope,
        ]);
    }
}
