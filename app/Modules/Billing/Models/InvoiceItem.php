<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $table = 'bill_invoice_items';

    protected $fillable = ['invoice_id', 'line_number', 'concept', 'amount_pesos'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillInvoice::class, 'invoice_id');
    }
}
