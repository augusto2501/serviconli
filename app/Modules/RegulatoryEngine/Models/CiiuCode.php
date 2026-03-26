<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class CiiuCode extends Model
{
    protected $table = 'cfg_ciiu_codes';

    protected $fillable = ['code', 'description', 'arl_risk_class'];
}
