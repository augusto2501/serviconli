<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\PILALiquidation\Models\PilaLiquidation;

final class PilaLiquidationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PilaLiquidation $pilaLiquidation): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PilaLiquidation $pilaLiquidation): bool
    {
        return true;
    }
}
