<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentDeadlineOverride extends Model
{
    protected $table = 'cfg_payment_deadline_overrides';

    protected $fillable = ['period_year', 'period_month', 'deadline_date', 'mora_date', 'reason'];

    protected function casts(): array
    {
        return [
            'deadline_date' => 'date',
            'mora_date' => 'date',
        ];
    }
}
