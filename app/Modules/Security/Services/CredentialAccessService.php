<?php

namespace App\Modules\Security\Services;

use App\Modules\Affiliates\Models\PortalCredential;
use App\Modules\Security\Models\CredentialAccessLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * RF-111 — log de acceso/descifrado de credenciales cifradas.
 *
 * @see DOCUMENTO_RECTOR §14.3
 */
final class CredentialAccessService
{
    public static function logAccess(PortalCredential $credential, string $action = 'DECRYPT'): CredentialAccessLog
    {
        return CredentialAccessLog::query()->create([
            'user_id' => Auth::id(),
            'credential_id' => $credential->id,
            'action' => $action,
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Obtener el password desencriptado y registrar el acceso.
     */
    public static function decryptAndLog(PortalCredential $credential): string
    {
        self::logAccess($credential, 'DECRYPT');

        return $credential->password;
    }
}
