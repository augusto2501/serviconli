<?php

namespace App\Modules\Affiliates\Models;

// RF-012–RF-014

use App\Modules\Billing\Models\BillInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReentryProcess extends Model
{
    protected $table = 'wf_reentry_processes';

    protected $fillable = [
        'status',
        'current_step',
        'affiliate_id',
        'step1_payload',
        'step2_payload',
        'step3_payload',
        'bill_invoice_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'step1_payload' => 'array',
            'step2_payload' => 'array',
            'step3_payload' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /** @return BelongsTo<BillInvoice, $this> */
    public function billInvoice(): BelongsTo
    {
        return $this->belongsTo(BillInvoice::class, 'bill_invoice_id');
    }
}
