<?php

namespace App\Modules\Disabilities\Models;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\RegulatoryEngine\Models\DiagnosisCie10;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateDisability extends Model
{
    protected $table = 'dis_affiliate_disabilities';

    protected $fillable = [
        'affiliate_id',
        'source',
        'subtype_code',
        'diagnosis_cie10_id',
        'start_date',
        'end_date',
        'submitted_documents',
        'cumulative_days',
        'over_180_alert',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'submitted_documents' => 'array',
            'over_180_alert' => 'boolean',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }

    public function diagnosisCie10(): BelongsTo
    {
        return $this->belongsTo(DiagnosisCie10::class, 'diagnosis_cie10_id');
    }

    /** @return HasMany<DisabilityExtension, $this> */
    public function extensions(): HasMany
    {
        return $this->hasMany(DisabilityExtension::class, 'disability_id');
    }
}
