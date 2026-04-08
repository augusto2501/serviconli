<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Advisors\Models\AdvisorCommission;

final class AdvisorCommissionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function update(User $user, AdvisorCommission $advisorCommission): bool
    {
        return true;
    }
}
