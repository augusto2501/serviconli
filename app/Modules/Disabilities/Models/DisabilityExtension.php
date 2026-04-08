<?php

namespace App\Modules\Disabilities\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisabilityExtension extends Model
{
    protected $table = 'dis_disability_extensions';

    protected $fillable = [
        'disability_id',
        'start_date',
        'end_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function disability(): BelongsTo
    {
        return $this->belongsTo(AffiliateDisability::class, 'disability_id');
    }
}
