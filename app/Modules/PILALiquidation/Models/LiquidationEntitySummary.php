<?php

namespace App\Modules\PILALiquidation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Totales por entidad PILA dentro de un lote.
 *
 * @see DOCUMENTO_RECTOR §4 Grupo D — pay_liquidation_entity_summary
 */
class LiquidationEntitySummary extends Model
{
    protected $table = 'pay_liquidation_entity_summary';

    protected $fillable = [
        'batch_id',
        'entity_pila_code',
        'subsystem',
        'amount_pesos',
    ];

    /** @return BelongsTo<LiquidationBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(LiquidationBatch::class, 'batch_id');
    }
}
