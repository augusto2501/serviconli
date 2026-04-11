<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\PILALiquidation\Models\PilaLiquidation;

// RF-108 — RBAC con Spatie Permission

final class PilaLiquidationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('liquidation.view');
    }

    public function view(User $user, PilaLiquidation $liquidation): bool
    {
        return $user->hasPermissionTo('liquidation.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('liquidation.create');
    }

    public function update(User $user, PilaLiquidation $liquidation): bool
    {
        return $user->hasPermissionTo('liquidation.confirm');
    }
}
