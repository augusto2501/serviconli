<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptConcept extends Model
{
    protected $table = 'cfg_receipt_concepts';

    protected $fillable = ['code', 'name'];
}
