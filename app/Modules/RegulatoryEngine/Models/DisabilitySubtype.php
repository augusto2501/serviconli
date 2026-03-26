<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisabilitySubtype extends Model
{
    protected $table = 'cfg_disability_subtypes';

    protected $fillable = ['disability_type_id', 'code', 'name', 'required_documents'];

    protected function casts(): array
    {
        return [
            'required_documents' => 'array',
        ];
    }

    public function disabilityType(): BelongsTo
    {
        return $this->belongsTo(DisabilityType::class, 'disability_type_id');
    }
}
