<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class CancellationReason extends Model
{
    protected $table = 'cfg_cancellation_reasons';

    protected $fillable = ['code', 'name'];
}
