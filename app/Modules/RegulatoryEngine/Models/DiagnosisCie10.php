<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class DiagnosisCie10 extends Model
{
    protected $table = 'cfg_diagnosis_cie10';

    protected $fillable = ['code', 'description'];
}
