<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributorTypeSubsystem extends Model
{
    protected $table = 'cfg_contributor_type_subsystems';

    protected $fillable = ['contributor_type_id', 'subsystem', 'is_required', 'distribution_percent'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'distribution_percent' => 'decimal:4',
        ];
    }

    public function contributorType(): BelongsTo
    {
        return $this->belongsTo(ContributorType::class, 'contributor_type_id');
    }
}
