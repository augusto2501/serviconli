<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\ThirdParties\Models\BankDeposit;

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
