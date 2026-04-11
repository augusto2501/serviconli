<?php

namespace App\Policies;

use App\Models\User;

// RF-108 — RBAC con Spatie Permission

final class ReentryProcessPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('reentry.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('reentry.update');
    }
}
