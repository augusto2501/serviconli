<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Advisors\Models\Advisor;

// RF-108 — RBAC con Spatie Permission

final class AdvisorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('advisors.view');
    }

    public function view(User $user, Advisor $advisor): bool
    {
        return $user->hasPermissionTo('advisors.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('advisors.create');
    }

    public function update(User $user, Advisor $advisor): bool
    {
        return $user->hasPermissionTo('advisors.update');
    }

    public function delete(User $user, Advisor $advisor): bool
    {
        return $user->hasPermissionTo('advisors.delete');
    }
}
