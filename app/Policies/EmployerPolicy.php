<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Employers\Models\Employer;

// RF-108 — RBAC con Spatie Permission

final class EmployerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('employers.view');
    }

    public function view(User $user, Employer $employer): bool
    {
        return $user->hasPermissionTo('employers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('employers.create');
    }

    public function update(User $user, Employer $employer): bool
    {
        return $user->hasPermissionTo('employers.update');
    }

    public function delete(User $user, Employer $employer): bool
    {
        return $user->hasPermissionTo('employers.delete');
    }
}
