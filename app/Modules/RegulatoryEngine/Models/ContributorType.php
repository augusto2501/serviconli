<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContributorType extends Model
{
    protected $table = 'cfg_contributor_types';

    protected $fillable = ['code', 'name', 'subsystems', 'ibc_rules', 'legal_basis', 'is_active'];

    protected function casts(): array
    {
        return [
            'subsystems' => 'array',
            'ibc_rules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subsystemsPivot(): HasMany
    {
        return $this->hasMany(ContributorTypeSubsystem::class, 'contributor_type_id');
    }
}
