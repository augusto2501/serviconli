<?php

namespace App\Modules\Affiliates\Models;

// RF-001 — proceso wizard 6 pasos

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentProcess extends Model
{
    protected $table = 'wf_enrollment_processes';

    protected $fillable = [
        'status',
        'current_step',
        'step1_payload',
        'step2_payload',
        'step3_payload',
        'step4_payload',
        'step5_payload',
        'affiliate_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'step1_payload' => 'array',
            'step2_payload' => 'array',
            'step3_payload' => 'array',
            'step4_payload' => 'array',
            'step5_payload' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
