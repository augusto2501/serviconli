<?php

namespace App\Policies;

use App\Models\User;

final class BankDepositPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }
}
