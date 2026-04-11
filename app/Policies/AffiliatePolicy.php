<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Affiliates\Models\Affiliate;

// RF-108 — RBAC con Spatie Permission

final class AffiliatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('affiliates.view');
    }

    public function view(User $user, Affiliate $affiliate): bool
    {
        return $user->hasPermissionTo('affiliates.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('affiliates.create');
    }

    public function update(User $user, Affiliate $affiliate): bool
    {
        return $user->hasPermissionTo('affiliates.update');
    }

    public function delete(User $user, Affiliate $affiliate): bool
    {
        return $user->hasPermissionTo('affiliates.delete');
    }
}
