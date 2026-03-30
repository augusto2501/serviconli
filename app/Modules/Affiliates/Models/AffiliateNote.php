<?php

namespace App\Modules\Affiliates\Models;

// DOCUMENTO_RECTOR §4 — afl_affiliate_notes; RF-019

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateNote extends Model
{
    public $timestamps = false;

    protected $table = 'afl_affiliate_notes';

    protected $fillable = [
        'affiliate_id',
        'note',
        'note_type',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
