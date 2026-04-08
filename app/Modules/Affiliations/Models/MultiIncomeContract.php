<?php

namespace App\Modules\Affiliations\Models;

// DOCUMENTO_RECTOR §3.3 Grupo C — RF-030 contratos multi-ingreso independientes

use App\Modules\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MultiIncomeContract extends Model
{
    protected $table = 'afl_multi_income_contracts';

    protected $fillable = [
        'affiliate_id',
        'period_year',
        'period_month',
        'contract_description',
        'income_pesos',
        'ibc_contribution_pesos',
        'created_by',
    ];

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
