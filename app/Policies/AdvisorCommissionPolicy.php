<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Advisors\Models\AdvisorCommission;

// RF-108 — RBAC con Spatie Permission

final class AdvisorCommissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('commissions.view');
    }

    public function update(User $user, AdvisorCommission $advisorCommission): bool
    {
        return $user->hasPermissionTo('commissions.update');
    }
}
