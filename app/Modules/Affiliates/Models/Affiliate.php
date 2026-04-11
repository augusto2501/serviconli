<?php

namespace App\Modules\Affiliates\Models;

// DOCUMENTO_RECTOR §4 Grupo B — afl_affiliates

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliations\Models\AffiliatePayer;
use App\Modules\Affiliations\Models\SocialSecurityProfile;
use App\Modules\RegulatoryEngine\Models\AffiliateStatus;
use App\Modules\Security\Traits\Auditable;
use App\Modules\Security\Traits\SoftDeletesWithReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Affiliate extends Model
{
    use Auditable, SoftDeletes, SoftDeletesWithReason;

    protected $table = 'afl_affiliates';

    protected $fillable = [
        'person_id',
        'client_type',
        'status_id',
        'mora_status',
        'ips_code',
        'has_discount',
        'discount_notes',
        'is_type_51',
        'subtipo',
        'operational_notes',
        'payment_notes',
    ];

    protected function casts(): array
    {
        return [
            'client_type' => AffiliateClientType::class,
            'has_discount' => 'boolean',
            'is_type_51' => 'boolean',
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<AffiliateStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(AffiliateStatus::class, 'status_id');
    }

    /** @return HasOne<SocialSecurityProfile, $this> */
    public function currentSocialSecurityProfile(): HasOne
    {
        return $this->hasOne(SocialSecurityProfile::class, 'affiliate_id')
            ->whereNull('valid_until')
            ->latestOfMany('valid_from');
    }

    /** Vínculo vigente afiliado–pagador (RF-028 / listado Mis Afiliados). */
    /** @return HasOne<AffiliatePayer, $this> */
    public function currentAffiliatePayer(): HasOne
    {
        return $this->hasOne(AffiliatePayer::class, 'affiliate_id')
            ->whereNull('end_date')
            ->latestOfMany('start_date');
    }

    /** @return HasMany<PortalCredential, $this> */
    public function portalCredentials(): HasMany
    {
        return $this->hasMany(PortalCredential::class);
    }
}
