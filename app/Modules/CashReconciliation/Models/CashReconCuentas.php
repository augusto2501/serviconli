<?php

namespace App\Modules\CashReconciliation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashReconCuentas extends Model
{
    protected $table = 'cash_recon_cuentas';

    protected $fillable = [
        'reconciliation_id', 'total_affiliations_cuentas', 'total_contributions_cuentas', 'total_admin_cuentas',
        'total_efectivo', 'total_consignacion', 'total_credito', 'total_cuenta_cobro',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(DailyReconciliation::class, 'reconciliation_id');
    }
}
