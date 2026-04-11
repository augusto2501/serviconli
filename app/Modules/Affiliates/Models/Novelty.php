<?php

namespace App\Modules\Affiliates\Models;

use App\Modules\Security\Traits\Auditable;
use App\Modules\Security\Traits\SoftDeletesWithReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Novedad PILA por afiliado y período.
 *
 * @see DOCUMENTO_RECTOR §3.4, §4 Grupo B, RF-061..RF-066
 */
class Novelty extends Model
{
    use Auditable, SoftDeletes, SoftDeletesWithReason;

    protected $table = 'afl_novelties';

    protected $fillable = [
        'affiliate_id',
        'payer_id',
        'period_year',
        'period_month',
        'novelty_type_code',
        'start_date',
        'end_date',
        'previous_entity_id',
        'new_entity_id',
        'previous_value',
        'new_value',
        'retirement_scope',
        'retirement_cause',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function isRetirement(): bool
    {
        return $this->novelty_type_code === 'RET';
    }

    public function isTransfer(): bool
    {
        return in_array($this->novelty_type_code, ['TAE', 'TAP'], true);
    }

    public function isSalaryChange(): bool
    {
        return in_array($this->novelty_type_code, ['VSP', 'VST'], true);
    }
}
