<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Advisors\Models\Advisor;

final class AdvisorPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Advisor $advisor): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Advisor $advisor): bool
    {
        return true;
    }

    public function delete(User $user, Advisor $advisor): bool
    {
        return true;
    }
}
