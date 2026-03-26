<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalException extends Model
{
    protected $table = 'cfg_operational_exceptions';

    protected $fillable = [
        'exception_type', 'target_type', 'target_id', 'value', 'reason',
        'authorized_by', 'valid_from', 'valid_until', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
