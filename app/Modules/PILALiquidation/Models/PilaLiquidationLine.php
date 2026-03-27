<?php

namespace App\Modules\PILALiquidation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PilaLiquidationLine extends Model
{
    protected $table = 'pila_liquidation_lines';

    protected $fillable = [
        'pila_liquidation_id',
        'line_number',
        'period_year',
        'period_month',
        'raw_ibc_pesos',
        'ibc_rounded_pesos',
        'days_late',
        'payment_deadline_date',
        'subsystem_amounts_pesos',
        'total_social_security_pesos',
    ];

    protected function casts(): array
    {
        return [
            'payment_deadline_date' => 'date',
            'subsystem_amounts_pesos' => 'array',
        ];
    }

    /** @return BelongsTo<PilaLiquidation, $this> */
    public function liquidation(): BelongsTo
    {
        return $this->belongsTo(PilaLiquidation::class, 'pila_liquidation_id');
    }
}
