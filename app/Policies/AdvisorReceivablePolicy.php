<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\ThirdParties\Models\AdvisorReceivable;

final class AdvisorReceivablePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function update(User $user, AdvisorReceivable $advisorReceivable): bool
    {
        return true;
    }
}
