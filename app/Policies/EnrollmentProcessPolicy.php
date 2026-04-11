<?php

namespace App\Policies;

use App\Models\User;

// RF-108 — RBAC con Spatie Permission

final class EnrollmentProcessPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('enrollment.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('enrollment.update');
    }
}
