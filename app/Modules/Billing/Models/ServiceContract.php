<?php

namespace App\Modules\Billing\Models;

use App\Modules\Affiliations\Models\Payer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceContract extends Model
{
    protected $table = 'bill_service_contracts';

    protected $fillable = [
        'payer_id', 'plan', 'tarifa_admin_pesos', 'tarifa_affiliation_pesos',
        'vigencia_start', 'vigencia_end', 'generates_cuenta_cobro', 'status',
    ];

    protected function casts(): array
    {
        return [
            'vigencia_start' => 'date',
            'vigencia_end' => 'date',
            'generates_cuenta_cobro' => 'boolean',
        ];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }
}
