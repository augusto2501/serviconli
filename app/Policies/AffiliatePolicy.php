<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Affiliates\Models\Affiliate;

// BC-13 — ampliar con roles/permisos cuando existan en cfg

final class AffiliatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Affiliate $affiliate): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Affiliate $affiliate): bool
    {
        return true;
    }

    public function delete(User $user, Affiliate $affiliate): bool
    {
        return true;
    }
}
