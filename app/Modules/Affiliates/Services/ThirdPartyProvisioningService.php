<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\Models\Affiliate;

/**
 * RF-010 — registro de tercero contable cuando exista catálogo formal (iteración futura).
 */
final class ThirdPartyProvisioningService
{
    public function ensureForAffiliate(Affiliate $affiliate): void
    {
        // Stub intencional: enlazar con módulo de terceros / ERP cuando aplique.
    }
}
