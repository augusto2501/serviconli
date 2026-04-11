<?php

namespace App\Policies;

use App\Models\User;

// RF-108 — RBAC con Spatie Permission

final class AdvisorCommissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('commissions.view');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('commissions.update');
    }
}
