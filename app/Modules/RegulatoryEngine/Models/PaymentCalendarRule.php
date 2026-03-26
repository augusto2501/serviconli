<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentCalendarRule extends Model
{
    protected $table = 'cfg_payment_calendar_rules';

    protected $fillable = ['digit_range_start', 'digit_range_end', 'business_day'];
}
