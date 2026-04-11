<?php

namespace App\Modules\Security\Services;

use App\Modules\Security\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * RF-109 — servicio de auditoría transversal.
 *
 * Registra acciones modificativas (created, updated, deleted)
 * con valores antes/después, usuario, IP y user-agent.
 *
 * @see DOCUMENTO_RECTOR §14.2
 */
final class AuditLogService
{
    public static function log(string $action, Model $model, ?array $oldValues = null, ?array $newValues = null): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    public static function logCreated(Model $model): AuditLog
    {
        return self::log('created', $model, null, $model->getAttributes());
    }

    public static function logUpdated(Model $model): AuditLog
    {
        $dirty = $model->getDirty();
        $original = array_intersect_key($model->getOriginal(), $dirty);

        return self::log('updated', $model, $original, $dirty);
    }

    public static function logDeleted(Model $model): AuditLog
    {
        return self::log('deleted', $model, $model->getOriginal(), null);
    }
}
