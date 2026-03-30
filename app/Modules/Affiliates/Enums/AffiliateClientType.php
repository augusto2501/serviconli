<?php

namespace App\Modules\Affiliates\Enums;

// DOCUMENTO_RECTOR §4 Grupo B — client_type en afl_affiliates

enum AffiliateClientType: string
{
    case SERVICONLI = 'SERVICONLI';
    case INDEPENDIENTE = 'INDEPENDIENTE';
    case DEPENDIENTE = 'DEPENDIENTE';
    case COLOMBIANO_EXTERIOR = 'COLOMBIANO_EXTERIOR';
}
