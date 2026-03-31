<?php

namespace App\Modules\PILALiquidation\Models;

use App\Modules\Affiliations\Models\Payer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lote de liquidación PILA — Flujo 4.
 *
 * Portado de Access Form_004 (liquidación masiva por empleador/pagador).
 *
 * @see DOCUMENTO_RECTOR §6 Flujo 4, §4 Grupo D
 */
class LiquidationBatch extends Model
{
    protected $table = 'pay_liquidation_batches';

    protected $fillable = [
        'payer_id',
        'period_year',
        'period_month',
        'cotization_year',
        'cotization_month',
        'planilla_type',
        'status',
        'total_health',
        'total_pension',
        'total_arl',
        'total_ccf',
        'total_solidarity',
        'total_upc',
        'total_admin',
        'grand_total',
        'cant_affiliates',
        'operator_id',
        'planilla_number',
        'payment_date',
        'branch_code',
        'rounding_adjustment_total',
        'valor_calculado_sistema',
        'valor_pagado_operador',
        'diferencia_reconciliacion',
        'estado_reconciliacion',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
        ];
    }

    /** @return BelongsTo<Payer, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    /** @return HasMany<LiquidationBatchLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(LiquidationBatchLine::class, 'batch_id');
    }

    /** @return HasMany<LiquidationEntitySummary, $this> */
    public function entitySummaries(): HasMany
    {
        return $this->hasMany(LiquidationEntitySummary::class, 'batch_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'BORRADOR';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'LIQUIDADO';
    }
}
