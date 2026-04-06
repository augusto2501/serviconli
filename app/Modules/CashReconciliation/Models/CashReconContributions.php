<?php

namespace App\Modules\CashReconciliation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashReconContributions extends Model
{
    protected $table = 'cash_recon_contributions';

    protected $fillable = [
        'reconciliation_id', 'total_aporte_pos', 'total_admin', 'total_interest_mora', 'provision_mora',
        'total_efectivo', 'total_consignacion', 'total_credito', 'total_cuenta_cobro',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(DailyReconciliation::class, 'reconciliation_id');
    }
}
