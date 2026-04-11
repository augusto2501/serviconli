<?php

namespace App\Modules\Security\Models;

use App\Models\User;
use App\Modules\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-110 — solicitud de derechos Habeas Data (Ley 1581/2012).
 *
 * Tipos: CONSULTA, RECTIFICACION, SUPRESION, REVOCACION.
 *
 * @see DOCUMENTO_RECTOR §14.3
 */
class GdprRequest extends Model
{
    protected $table = 'gdpr_requests';

    protected $fillable = [
        'affiliate_id',
        'requested_by',
        'type',
        'description',
        'status',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
