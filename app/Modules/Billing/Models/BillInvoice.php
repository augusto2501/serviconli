<?php

namespace App\Modules\Billing\Models;

// BC-06 — facturación mínima; RF-014 recibo reingreso tipo "03"

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliations\Models\Payer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillInvoice extends Model
{
    protected $table = 'bill_invoices';

    protected $fillable = [
        'public_number', 'radicado', 'fecha', 'service_type_code',
        'affiliate_id', 'payer_id', 'cuenta_cobro_id',
        'tipo', 'payment_method', 'total_pesos', 'amounts', 'estado',
        'cancellation_reason', 'cancellation_motive', 'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'amounts' => 'array',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class, 'payer_id');
    }

    public function cuentaCobro(): BelongsTo
    {
        return $this->belongsTo(CuentaCobro::class, 'cuenta_cobro_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentReceived::class, 'invoice_id');
    }

    public function isActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function isAnulado(): bool
    {
        return $this->estado === 'ANULADO';
    }
}
