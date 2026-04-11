<?php

namespace App\Modules\Employers\Models;

// RF-024–027

use App\Modules\Security\Traits\Auditable;
use App\Modules\Security\Traits\SoftDeletesWithReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employer extends Model
{
    use Auditable, SoftDeletes, SoftDeletesWithReason;

    protected $table = 'empl_employers';

    protected $fillable = [
        'nit_body',
        'digito_verificacion',
        'razon_social',
        'nombre_corto',
        'representante_legal',
        'representante_documento',
        'tipo_persona',
        'naturaleza_juridica',
        'actividad_economica_code',
        'address',
        'city_name',
        'department_name',
        'phone',
        'email',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'digito_verificacion' => 'integer',
        ];
    }
}
