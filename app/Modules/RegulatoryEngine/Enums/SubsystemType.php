<?php

namespace App\Modules\RegulatoryEngine\Enums;

enum SubsystemType: string
{
    case SALUD = 'SALUD';
    case PENSION = 'PENSION';
    case ARL = 'ARL';
    case CCF = 'CCF';
}
