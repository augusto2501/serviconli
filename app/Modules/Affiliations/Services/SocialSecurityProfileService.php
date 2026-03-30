<?php

namespace App\Modules\Affiliations\Services;

// RF-028 / RF-029 — perfil vigente a fecha sin sobrescribir historial

use App\Modules\Affiliations\Models\SocialSecurityProfile;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class SocialSecurityProfileService
{
    /**
     * Perfil de seguridad social aplicable a la fecha indicada (mayor valid_from entre los que cubren la fecha).
     */
    public function currentForAffiliate(int $affiliateId, CarbonInterface|string $onDate): ?SocialSecurityProfile
    {
        $d = $onDate instanceof CarbonInterface
            ? $onDate->toDateString()
            : Carbon::parse($onDate)->toDateString();

        return SocialSecurityProfile::query()
            ->where('affiliate_id', $affiliateId)
            ->whereDate('valid_from', '<=', $d)
            ->where(function ($q) use ($d): void {
                $q->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', $d);
            })
            ->orderByDesc('valid_from')
            ->first();
    }
}
