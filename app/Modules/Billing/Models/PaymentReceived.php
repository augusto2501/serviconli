<?php

namespace App\Modules\Billing\Models;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliations\Models\Payer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReceived extends Model
{
    protected $table = 'bill_payments_received';

    protected $fillable = [
        'invoice_id', 'cuenta_cobro_id', 'affiliate_id', 'payer_id',
        'payment_method', 'amount_pesos', 'payment_date',
        'bank_name', 'bank_reference', 'status', 'received_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillInvoice::class, 'invoice_id');
    }

    public function cuentaCobro(): BelongsTo
    {
        return $this->belongsTo(CuentaCobro::class, 'cuenta_cobro_id');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }
}
