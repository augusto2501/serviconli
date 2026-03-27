<?php

namespace App\Modules\PILALiquidation\Models;

use App\Modules\PILALiquidation\Enums\PilaLiquidationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PilaLiquidation extends Model
{
    protected $table = 'pila_liquidations';

    protected $fillable = [
        'public_id',
        'status',
        'contributor_type_code',
        'arl_risk_class',
        'payment_date',
        'document_last_two_digits',
        'target_type',
        'target_id',
        'subject_type',
        'subject_id',
        'total_social_security_pesos',
        'subsystem_totals_pesos',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'subsystem_totals_pesos' => 'array',
            'status' => PilaLiquidationStatus::class,
        ];
    }

    /** @return HasMany<PilaLiquidationLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PilaLiquidationLine::class)->orderBy('line_number');
    }
}
