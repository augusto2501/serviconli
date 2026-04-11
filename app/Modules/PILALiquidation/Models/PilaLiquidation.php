<?php

namespace App\Modules\PILALiquidation\Models;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\PILALiquidation\Enums\PilaLiquidationStatus;
use App\Modules\Security\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PilaLiquidation extends Model
{
    use Auditable;

    protected $table = 'pila_liquidations';

    protected $fillable = [
        'public_id',
        'status',
        'contributor_type_code',
        'arl_risk_class',
        'payment_date',
        'document_last_two_digits',
        'target_type',
        'target_id',
        'affiliate_id',
        'total_social_security_pesos',
        'subsystem_totals_pesos',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'subsystem_totals_pesos' => 'array',
            'status' => PilaLiquidationStatus::class,
        ];
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /** @return HasMany<PilaLiquidationLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PilaLiquidationLine::class)->orderBy('line_number');
    }
}
