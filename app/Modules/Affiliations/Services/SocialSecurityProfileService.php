<?php

namespace App\Modules\Affiliations\Services;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliations\Models\SocialSecurityProfile;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Versionado temporal de perfiles de seguridad social — RF-028, RF-029.
 *
 * Cada cambio de entidad (EPS, AFP, ARL, CCF), tarifa o salario crea una nueva
 * versión con valid_from y valid_until, sin sobreescribir la versión anterior.
 *
 * @see DOCUMENTO_RECTOR §4 Grupo B, RF-029, RF-065, RF-066
 */
final class SocialSecurityProfileService
{
    /**
     * Perfil de seguridad social aplicable a la fecha indicada.
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

    /**
     * RF-065: Versiona perfil por traslado de entidad (TAE/TAP).
     * Cierra versión actual (valid_until = hoy) y crea nueva con la entidad cambiada.
     */
    public function versionProfileForTransfer(
        Affiliate $affiliate,
        string $entityField,
        int $newEntityId,
    ): SocialSecurityProfile {
        $current = $this->currentForAffiliate($affiliate->id, now());

        if ($current !== null) {
            $current->valid_until = now()->toDateString();
            $current->save();
        }

        $newData = $current ? $current->toArray() : [];
        unset($newData['id'], $newData['created_at'], $newData['updated_at'], $newData['valid_until']);

        $newData['affiliate_id'] = $affiliate->id;
        $newData[$entityField] = $newEntityId;
        $newData['valid_from'] = now()->toDateString();
        $newData['valid_until'] = null;

        return SocialSecurityProfile::query()->create($newData);
    }

    /**
     * RF-066: Versiona perfil por cambio de salario (VSP/VST).
     * Cierra versión actual y crea nueva con nuevo IBC.
     */
    public function versionProfileForSalaryChange(
        Affiliate $affiliate,
        int $newIbcPesos,
    ): SocialSecurityProfile {
        $current = $this->currentForAffiliate($affiliate->id, now());

        if ($current !== null) {
            $current->valid_until = now()->toDateString();
            $current->save();
        }

        $newData = $current ? $current->toArray() : [];
        unset($newData['id'], $newData['created_at'], $newData['updated_at'], $newData['valid_until']);

        $newData['affiliate_id'] = $affiliate->id;
        $newData['ibc'] = $newIbcPesos;
        $newData['valid_from'] = now()->toDateString();
        $newData['valid_until'] = null;

        return SocialSecurityProfile::query()->create($newData);
    }
}
