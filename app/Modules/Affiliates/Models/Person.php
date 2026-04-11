<?php

namespace App\Modules\Affiliates\Models;

// DOCUMENTO_RECTOR §4 Grupo B — core_people

use App\Modules\Security\Traits\Auditable;
use App\Modules\Security\Traits\SoftDeletesWithReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use Auditable, SoftDeletes, SoftDeletesWithReason;

    protected $table = 'core_people';

    protected $fillable = [
        'document_type',
        'document_number',
        'first_name',
        'second_name',
        'first_surname',
        'second_surname',
        'birth_date',
        'gender',
        'marital_status',
        'address',
        'neighborhood',
        'city_code',
        'city_name',
        'department_code',
        'department_name',
        'phone1',
        'phone2',
        'cellphone',
        'email',
        'birth_city',
        'birth_department',
        'is_foreigner',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_foreigner' => 'boolean',
        ];
    }

    /** @return HasOne<Affiliate, $this> */
    public function affiliate(): HasOne
    {
        return $this->hasOne(Affiliate::class);
    }
}
