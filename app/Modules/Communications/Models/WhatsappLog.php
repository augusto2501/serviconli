<?php

namespace App\Modules\Communications\Models;

use App\Models\User;
use App\Modules\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappLog extends Model
{
    protected $table = 'comm_whatsapp_logs';

    protected $fillable = [
        'affiliate_id',
        'template_code',
        'to_number',
        'provider',
        'external_id',
        'status',
        'payload',
        'error_message',
        'triggered_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
