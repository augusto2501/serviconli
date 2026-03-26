<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class ValidationRule extends Model
{
    protected $table = 'cfg_validation_rules';

    protected $fillable = ['code', 'rule_expression', 'error_message', 'severity'];
}
