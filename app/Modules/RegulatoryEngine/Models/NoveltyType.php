<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NoveltyType extends Model
{
    protected $table = 'cfg_novelty_types';

    protected $fillable = ['code', 'name', 'effect_days', 'effect_ibc', 'who_pays', 'legal_basis'];

    public function rules(): HasMany
    {
        return $this->hasMany(NoveltyRule::class, 'novelty_type_id');
    }
}
