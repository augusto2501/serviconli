<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PilaOperatorBranch extends Model
{
    protected $table = 'cfg_pila_operator_branches';

    protected $fillable = ['operator_id', 'branch_code', 'branch_name'];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(SSEntity::class, 'operator_id');
    }
}
