<?php

namespace App\Modules\Billing\Models;

// BC-06 — facturación mínima; RF-014 recibo reingreso tipo "03"

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliations\Models\Payer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillInvoice extends Model
{
    protected $table = 'bill_invoices';

    protected $fillable = [
        'public_number',
        'affiliate_id',
        'payer_id',
        'tipo',
        'payment_method',
        'total_pesos',
        'estado',
    ];

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
}
