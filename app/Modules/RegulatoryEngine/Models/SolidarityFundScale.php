<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class SolidarityFundScale extends Model
{
    protected $table = 'cfg_solidarity_fund_scale';

    protected $fillable = ['min_smmlv', 'rate', 'valid_from', 'valid_until'];

    protected function casts(): array
    {
        return [
            'min_smmlv' => 'decimal:2',
            'rate' => 'decimal:4',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }
}
