<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    protected $table = 'bill_quotations';

    protected $fillable = [
        'prospect_name', 'prospect_document', 'prospect_phone', 'prospect_email',
        'salary_pesos', 'contributor_type_code', 'arl_risk_class',
        'amounts', 'pdf_path', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amounts' => 'array',
        ];
    }
}
