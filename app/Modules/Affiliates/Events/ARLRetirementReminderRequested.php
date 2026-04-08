<?php

namespace App\Modules\Affiliates\Events;

use App\Modules\Affiliates\Models\Novelty;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * RN-28 — retiro total (X) o solo ARL (R): recordatorio en plataforma ARL.
 */
final class ARLRetirementReminderRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Novelty $novelty,
    ) {}
}
