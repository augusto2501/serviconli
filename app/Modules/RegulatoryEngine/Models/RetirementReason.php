<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class RetirementReason extends Model
{
    protected $table = 'cfg_retirement_reasons';

    protected $fillable = ['code', 'name'];
}
