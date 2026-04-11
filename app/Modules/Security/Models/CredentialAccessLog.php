<?php

namespace App\Modules\Security\Models;

use App\Models\User;
use App\Modules\Affiliates\Models\PortalCredential;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-111 — log de acceso a credenciales cifradas.
 *
 * @see DOCUMENTO_RECTOR §14.3
 */
class CredentialAccessLog extends Model
{
    protected $table = 'sec_credential_access_logs';

    protected $fillable = [
        'user_id',
        'credential_id',
        'action',
        'ip_address',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<PortalCredential, $this> */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(PortalCredential::class);
    }
}
