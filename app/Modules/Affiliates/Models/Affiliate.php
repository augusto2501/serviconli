<?php

namespace App\Modules\Affiliates\Models;

use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    protected $table = 'affiliates';

    protected $fillable = [
        'document_number',
        'first_name',
        'last_name',
    ];
}
