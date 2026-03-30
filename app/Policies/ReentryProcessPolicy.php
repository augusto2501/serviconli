<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Affiliates\Models\ReentryProcess;

final class ReentryProcessPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ReentryProcess $reentryProcess): bool
    {
        return true;
    }
}
