<?php

namespace App\Modules\RegulatoryEngine\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateStatus extends Model
{
    protected $table = 'cfg_affiliate_statuses';

    protected $fillable = ['code', 'name', 'sort_order'];
}
