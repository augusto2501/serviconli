<?php

namespace App\Modules\Advisors\Models;

// RF-099

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Advisor extends Model
{
    protected $table = 'sec_advisors';

    protected $fillable = [
        'code',
        'document_type',
        'document_number',
        'first_name',
        'last_name',
        'phone',
        'email',
        'commission_new',
        'commission_recurring',
        'authorizes_credits',
    ];

    protected function casts(): array
    {
        return [
            'authorizes_credits' => 'boolean',
        ];
    }

    /** @return HasMany<AdvisorCommission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(AdvisorCommission::class, 'advisor_id');
    }
}
