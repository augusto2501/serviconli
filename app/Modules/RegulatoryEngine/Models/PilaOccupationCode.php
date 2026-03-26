<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class PilaOccupationCode extends Model
{
    protected $table = 'cfg_pila_occupation_codes';

    protected $fillable = ['code', 'description'];
}
