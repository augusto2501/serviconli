<?php

namespace App\Modules\CashReconciliation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashReconAffiliations extends Model
{
    protected $table = 'cash_recon_affiliations';

    protected $fillable = [
        'reconciliation_id', 'total_receipts', 'total_affiliation_value', 'total_advisor_commission',
        'total_efectivo', 'total_consignacion', 'total_credito', 'total_cuenta_cobro',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(DailyReconciliation::class, 'reconciliation_id');
    }
}
