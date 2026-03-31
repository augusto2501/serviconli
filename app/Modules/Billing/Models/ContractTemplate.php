<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class ContractTemplate extends Model
{
    protected $table = 'bill_contract_templates';

    protected $fillable = ['name', 'slug', 'content_html', 'variables', 'version', 'is_active'];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
