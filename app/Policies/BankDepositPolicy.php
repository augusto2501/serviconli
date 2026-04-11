<?php

namespace App\Policies;

use App\Models\User;

// RF-108 — RBAC con Spatie Permission

final class BankDepositPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('deposits.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('deposits.create');
    }
}
