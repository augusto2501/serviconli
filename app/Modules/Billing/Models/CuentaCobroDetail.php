<?php

namespace App\Modules\Billing\Models;

use App\Modules\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuentaCobroDetail extends Model
{
    protected $table = 'bill_cuenta_cobro_details';

    protected $fillable = [
        'cuenta_cobro_id', 'affiliate_id',
        'health_pesos', 'pension_pesos', 'arl_pesos', 'ccf_pesos',
        'admin_pesos', 'affiliation_pesos', 'total_pesos',
    ];

    public function cuentaCobro(): BelongsTo
    {
        return $this->belongsTo(CuentaCobro::class, 'cuenta_cobro_id');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
