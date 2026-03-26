<?php

namespace App\Modules\RegulatoryEngine\Enums;

enum RoundingMethod: string
{
    /** RN-01: IBC al millar superior (ceil miles). */
    case IBC = 'IBC';

    /** Comportamiento histórico Access (redondeo entero COP). */
    case LEGACY = 'LEGACY';

    /** Salida compatible archivo / operador PILA. */
    case PILA = 'PILA';
}
