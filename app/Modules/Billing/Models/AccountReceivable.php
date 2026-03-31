<?php

namespace App\Modules\Billing\Models;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliations\Models\Payer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountReceivable extends Model
{
    protected $table = 'bill_accounts_receivable';

    protected $fillable = [
        'affiliate_id', 'payer_id', 'invoice_id', 'concept',
        'amount_pesos', 'balance_pesos', 'due_date', 'status', 'paid_date',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_date' => 'date',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillInvoice::class, 'invoice_id');
    }
}
