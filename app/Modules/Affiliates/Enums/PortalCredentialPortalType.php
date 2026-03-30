<?php

namespace App\Modules\Affiliates\Enums;

/** Tipo de portal / entidad para credenciales (alineado a columnas Excel legado). */
enum PortalCredentialPortalType: string
{
    case OPERATOR_PILA = 'OPERATOR_PILA';
    case EPS = 'EPS';
    case AFP = 'AFP';
    case ARL = 'ARL';
    case CCF = 'CCF';
}
