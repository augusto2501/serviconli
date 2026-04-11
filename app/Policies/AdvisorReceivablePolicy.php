<?php

namespace App\Policies;

use App\Models\User;

// RF-108 — RBAC con Spatie Permission

final class AdvisorReceivablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('receivables.view');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('receivables.update');
    }
}
