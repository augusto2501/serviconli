<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    protected $table = 'cfg_service_types';

    protected $fillable = ['code', 'name', 'default_fee'];

    protected function casts(): array
    {
        return [
            'default_fee' => 'decimal:2',
        ];
    }
}
