<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SSEntity extends Model
{
    protected $table = 'cfg_ss_entities';

    protected $fillable = ['pila_code', 'name', 'type', 'status', 'operator_format'];

    public function operatorBranches(): HasMany
    {
        return $this->hasMany(PilaOperatorBranch::class, 'operator_id');
    }
}
