<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Communications\Models\CommNotification;

final class CommNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function update(User $user, CommNotification $notification): bool
    {
        return $user->id === $notification->user_id;
    }
}
