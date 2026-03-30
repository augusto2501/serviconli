<?php

namespace App\Modules\Affiliations\Models;

// DOCUMENTO_RECTOR §4 — afl_payers

use App\Modules\Affiliates\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payer extends Model
{
    protected $table = 'afl_payers';

    protected $fillable = [
        'person_id',
        'nit',
        'digito_verificacion',
        'razon_social',
        'status',
        'pila_operator_code',
    ];

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return HasMany<AffiliatePayer, $this> */
    public function affiliatePayers(): HasMany
    {
        return $this->hasMany(AffiliatePayer::class, 'payer_id');
    }
}
