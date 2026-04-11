<?php

namespace App\Modules\Security\Traits;

use App\Modules\Security\Services\AuditLogService;

/**
 * RF-109 — trait para modelos que requieren auditoría automática.
 *
 * Registra created/updated/deleted en sec_audit_logs.
 *
 * @see DOCUMENTO_RECTOR §14.2
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model): void {
            AuditLogService::logCreated($model);
        });

        static::updated(function ($model): void {
            if (count($model->getDirty()) > 0) {
                AuditLogService::logUpdated($model);
            }
        });

        static::deleted(function ($model): void {
            AuditLogService::logDeleted($model);
        });
    }
}
