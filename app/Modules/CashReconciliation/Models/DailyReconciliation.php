<?php

namespace App\Modules\CashReconciliation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DailyReconciliation extends Model
{
    protected $table = 'cash_daily_reconciliations';

    protected $fillable = [
        'business_date', 'user_id', 'status', 'opened_at', 'closed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function affiliationsLine(): HasOne
    {
        return $this->hasOne(CashReconAffiliations::class, 'reconciliation_id');
    }

    public function contributionsLine(): HasOne
    {
        return $this->hasOne(CashReconContributions::class, 'reconciliation_id');
    }

    public function cuentasLine(): HasOne
    {
        return $this->hasOne(CashReconCuentas::class, 'reconciliation_id');
    }

    public function dailyClose(): HasOne
    {
        return $this->hasOne(DailyClose::class, 'reconciliation_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'ABIERTO';
    }

    public function isClosed(): bool
    {
        return $this->status === 'CERRADO';
    }
}
