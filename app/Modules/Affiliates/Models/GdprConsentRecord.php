<?php

namespace App\Modules\Affiliates\Models;

// RF-009 — consentimiento Ley 1581/2012 (IP, user agent, fecha)

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdprConsentRecord extends Model
{
    protected $table = 'gdpr_consent_records';

    protected $fillable = [
        'enrollment_process_id',
        'affiliate_id',
        'ip_address',
        'user_agent',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EnrollmentProcess, $this> */
    public function enrollmentProcess(): BelongsTo
    {
        return $this->belongsTo(EnrollmentProcess::class, 'enrollment_process_id');
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }
}
