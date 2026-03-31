<?php

namespace App\Modules\Billing\Models;

use App\Modules\Affiliations\Models\Payer;
use App\Modules\PILALiquidation\Models\LiquidationBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuentaCobro extends Model
{
    protected $table = 'bill_cuentas_cobro';

    protected $fillable = [
        'payer_id', 'batch_id', 'cuenta_number', 'period_year', 'period_month',
        'period_cobro', 'period_servicio', 'generation_mode',
        'total_eps', 'total_afp', 'total_arl', 'total_ccf', 'total_admin', 'total_affiliation',
        'payment_date_1', 'total_1', 'payment_date_2', 'interest_mora', 'total_2',
        'status', 'payment_date', 'payment_amount',
        'cancellation_reason', 'cancellation_motive', 'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date_1' => 'date',
            'payment_date_2' => 'date',
            'payment_date' => 'date',
        ];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LiquidationBatch::class, 'batch_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(CuentaCobroDetail::class, 'cuenta_cobro_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BillInvoice::class, 'cuenta_cobro_id');
    }

    public function isPreCuenta(): bool
    {
        return $this->status === 'PRE_CUENTA';
    }

    public function isDefinitiva(): bool
    {
        return $this->status === 'DEFINITIVA';
    }

    public function isPagada(): bool
    {
        return $this->status === 'PAGADA';
    }

    public function isAnulada(): bool
    {
        return $this->status === 'ANULADA';
    }
}
