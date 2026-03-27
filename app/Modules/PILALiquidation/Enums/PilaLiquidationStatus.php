<?php

namespace App\Modules\PILALiquidation\Enums;

enum PilaLiquidationStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
