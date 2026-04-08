<?php

namespace App\Modules\ThirdParties\Models;

// RF-101

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankDeposit extends Model
{
    protected $table = 'tp_bank_deposits';

    protected $fillable = [
        'bank_name',
        'reference',
        'amount_pesos',
        'deposit_type',
        'expected_amount_pesos',
        'notes',
        'created_by',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
