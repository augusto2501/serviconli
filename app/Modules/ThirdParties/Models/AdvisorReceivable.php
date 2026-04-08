<?php

namespace App\Modules\ThirdParties\Models;

// RF-102

use App\Models\User;
use App\Modules\Advisors\Models\Advisor;
use App\Modules\Billing\Models\BillInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvisorReceivable extends Model
{
    protected $table = 'tp_advisor_receivables';

    protected $fillable = [
        'advisor_id',
        'bill_invoice_id',
        'amount_pesos',
        'status',
        'notes',
        'created_by',
    ];

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Advisor::class, 'advisor_id');
    }

    public function billInvoice(): BelongsTo
    {
        return $this->belongsTo(BillInvoice::class, 'bill_invoice_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
