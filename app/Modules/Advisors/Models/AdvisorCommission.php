<?php

namespace App\Modules\Advisors\Models;

// RF-100

use App\Models\User;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\EnrollmentProcess;
use App\Modules\Affiliates\Models\ReentryProcess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvisorCommission extends Model
{
    protected $table = 'bill_advisor_commissions';

    protected $fillable = [
        'public_number',
        'advisor_id',
        'affiliate_id',
        'enrollment_process_id',
        'reentry_process_id',
        'commission_type',
        'amount_pesos',
        'status',
        'created_by',
    ];

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Advisor::class, 'advisor_id');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }

    public function enrollmentProcess(): BelongsTo
    {
        return $this->belongsTo(EnrollmentProcess::class, 'enrollment_process_id');
    }

    public function reentryProcess(): BelongsTo
    {
        return $this->belongsTo(ReentryProcess::class, 'reentry_process_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
