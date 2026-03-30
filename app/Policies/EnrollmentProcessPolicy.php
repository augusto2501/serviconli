<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Affiliates\Models\EnrollmentProcess;

final class EnrollmentProcessPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, EnrollmentProcess $enrollmentProcess): bool
    {
        return true;
    }
}
