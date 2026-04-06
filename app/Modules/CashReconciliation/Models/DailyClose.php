<?php

namespace App\Modules\CashReconciliation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClose extends Model
{
    protected $table = 'cash_daily_close';

    protected $fillable = [
        'reconciliation_id', 'user_id', 'closed_at', 'concept_amounts', 'grand_total_pesos',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'concept_amounts' => 'array',
        ];
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(DailyReconciliation::class, 'reconciliation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
