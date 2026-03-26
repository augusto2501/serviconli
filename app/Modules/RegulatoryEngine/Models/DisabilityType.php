<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisabilityType extends Model
{
    protected $table = 'cfg_disability_types';

    protected $fillable = ['code', 'name', 'category'];

    public function subtypes(): HasMany
    {
        return $this->hasMany(DisabilitySubtype::class, 'disability_type_id');
    }
}
