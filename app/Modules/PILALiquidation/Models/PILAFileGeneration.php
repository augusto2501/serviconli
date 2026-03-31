<?php

namespace App\Modules\PILALiquidation\Models;

use App\Modules\Affiliations\Models\Payer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de generación de archivo PILA — Flujo 8.
 *
 * @see DOCUMENTO_RECTOR §4 Grupo M — pila_file_generations
 */
class PILAFileGeneration extends Model
{
    protected $table = 'pila_file_generations';

    protected $fillable = [
        'payer_id',
        'batch_id',
        'period_year',
        'period_month',
        'planilla_type',
        'operator_id',
        'branch_code',
        'planilla_number',
        'payment_date',
        'affiliates_count',
        'file_path',
        'file_format',
        'generated_by',
        'status',
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

    /** @return BelongsTo<LiquidationBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(LiquidationBatch::class, 'batch_id');
    }
}
