<?php

namespace App\Modules\Communications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommNotification extends Model
{
    protected $table = 'comm_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'read_at',
        'action_url',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
