<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class ConsecutiveFormat extends Model
{
    protected $table = 'cfg_consecutive_formats';

    protected $fillable = ['code', 'pattern'];
}
