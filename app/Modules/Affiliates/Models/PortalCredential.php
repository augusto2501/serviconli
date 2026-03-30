<?php

namespace App\Modules\Affiliates\Models;

// RF-015 — credenciales en claro por defecto; cifrado opcional (config serviconli.portal_credentials.encrypt)

use App\Modules\Affiliates\Enums\PortalCredentialPortalType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalCredential extends Model
{
    protected $table = 'afl_portal_credentials';

    protected $fillable = [
        'affiliate_id',
        'portal_type',
        'username',
        'password',
        'notes',
    ];

    protected function casts(): array
    {
        $casts = [
            'portal_type' => PortalCredentialPortalType::class,
        ];

        if (config('serviconli.portal_credentials.encrypt', false)) {
            $casts['password'] = 'encrypted';
        }

        return $casts;
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
