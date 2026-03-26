<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoveltyRule extends Model
{
    protected $table = 'cfg_novelty_rules';

    protected $fillable = ['novelty_type_id', 'subsystem', 'effect_type', 'formula'];

    public function noveltyType(): BelongsTo
    {
        return $this->belongsTo(NoveltyType::class, 'novelty_type_id');
    }
}
