<?php

namespace App\Modules\Affiliates\Models;

// DOCUMENTO_RECTOR §4 — afl_beneficiaries; RF-017

use App\Modules\RegulatoryEngine\Models\SSEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Beneficiary extends Model
{
    protected $table = 'afl_beneficiaries';

    protected $fillable = [
        'affiliate_id',
        'document_type',
        'document_number',
        'first_name',
        'surnames',
        'birth_date',
        'gender',
        'parentesco',
        'eps_entity_id',
        'student_cert_expires',
        'disability_type',
        'protection_end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'student_cert_expires' => 'date',
            'protection_end_date' => 'date',
        ];
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /** @return BelongsTo<SSEntity, $this> */
    public function epsEntity(): BelongsTo
    {
        return $this->belongsTo(SSEntity::class, 'eps_entity_id');
    }
}
