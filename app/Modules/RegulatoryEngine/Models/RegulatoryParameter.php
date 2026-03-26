<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class RegulatoryParameter extends Model
{
    protected $table = 'cfg_regulatory_parameters';

    protected $fillable = [
        'category', 'key', 'value', 'data_type', 'legal_basis', 'valid_from', 'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }
}
