<?php

namespace App\Modules\Affiliations\Models;

// DOCUMENTO_RECTOR §4 — afl_affiliate_payer

use App\Modules\Advisors\Models\Advisor;
use App\Modules\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliatePayer extends Model
{
    protected $table = 'afl_affiliate_payer';

    protected $fillable = [
        'affiliate_id',
        'payer_id',
        'start_date',
        'end_date',
        'contributor_type_code',
        'salary',
        'position',
        'occupation_code_768',
        'advisor_id',
        'enterprise_code',
        'enterprise_name',
        'status',
        'affiliation_paid',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'affiliation_paid' => 'boolean',
        ];
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /** @return BelongsTo<Payer, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class, 'payer_id');
    }

    /** @return BelongsTo<Advisor, $this> */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Advisor::class, 'advisor_id');
    }
}
