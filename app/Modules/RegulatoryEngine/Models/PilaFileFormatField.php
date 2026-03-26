<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class PilaFileFormatField extends Model
{
    protected $table = 'cfg_pila_file_format_fields';

    protected $fillable = ['record_type', 'field_name', 'position_start', 'length', 'pad_char', 'description'];
}
