<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class ColombianHoliday extends Model
{
    protected $table = 'cfg_colombian_holidays';

    protected $fillable = ['holiday_date', 'name', 'law_basis'];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
        ];
    }
}
