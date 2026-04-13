<?php

namespace App\Modules\Billing\Enums;

/**
 * RF-084 — catálogo de causales de anulación validadas.
 *
 * @see DOCUMENTO_RECTOR §5 RN-18
 */
enum CancellationReason: string
{
    case ERROR_DIGITACION = 'ERROR_DIGITACION';
    case DUPLICADO = 'DUPLICADO';
    case SOLICITUD_AFILIADO = 'SOLICITUD_AFILIADO';
    case ERROR_LIQUIDACION = 'ERROR_LIQUIDACION';
    case CAMBIO_MEDIO_PAGO = 'CAMBIO_MEDIO_PAGO';
    case CONSIGNACION_NO_VERIFICADA = 'CONSIGNACION_NO_VERIFICADA';
    case RETIRO_AFILIADO = 'RETIRO_AFILIADO';
    case OTRO = 'OTRO';
}
