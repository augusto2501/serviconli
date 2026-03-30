<?php

namespace App\Modules\Affiliations\Models;

// DOCUMENTO_RECTOR §4 — afl_social_security_profiles

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\RegulatoryEngine\Models\SSEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialSecurityProfile extends Model
{
    protected $table = 'afl_social_security_profiles';

    protected $fillable = [
        'affiliate_id',
        'eps_entity_id',
        'afp_entity_id',
        'arl_entity_id',
        'ccf_entity_id',
        'eps_tarifa',
        'afp_tarifa',
        'arl_tarifa',
        'arl_risk_class',
        'ccf_tarifa',
        'ibc',
        'admin_fee',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'eps_tarifa' => 'decimal:4',
            'afp_tarifa' => 'decimal:4',
            'arl_tarifa' => 'decimal:4',
            'ccf_tarifa' => 'decimal:4',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /** @return BelongsTo<SSEntity, $this> */
    public function epsEntity(): BelongsTo
    {
        return $this->belongsTo(SSEntity::class, 'eps_entity_id');
    }

    /** @return BelongsTo<SSEntity, $this> */
    public function afpEntity(): BelongsTo
    {
        return $this->belongsTo(SSEntity::class, 'afp_entity_id');
    }

    /** @return BelongsTo<SSEntity, $this> */
    public function arlEntity(): BelongsTo
    {
        return $this->belongsTo(SSEntity::class, 'arl_entity_id');
    }

    /** @return BelongsTo<SSEntity, $this> */
    public function ccfEntity(): BelongsTo
    {
        return $this->belongsTo(SSEntity::class, 'ccf_entity_id');
    }
}
