<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Communications\Models\CommNotification;

// RF-108 — RBAC con Spatie Permission

final class CommNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('notifications.view');
    }

    public function update(User $user, CommNotification $notification): bool
    {
        if ($notification->user_id !== null && $notification->user_id !== $user->id) {
            return false;
        }

        return $user->hasPermissionTo('notifications.update');
    }
}
