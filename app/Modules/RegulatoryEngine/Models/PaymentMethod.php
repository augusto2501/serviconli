<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $table = 'cfg_payment_methods';

    protected $fillable = ['code', 'name'];
}
