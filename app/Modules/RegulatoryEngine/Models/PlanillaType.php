<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class PlanillaType extends Model
{
    protected $table = 'cfg_planilla_types';

    protected $fillable = ['code', 'name', 'allowed_contributors', 'allowed_novelties'];

    protected function casts(): array
    {
        return [
            'allowed_contributors' => 'array',
            'allowed_novelties' => 'array',
        ];
    }
}
