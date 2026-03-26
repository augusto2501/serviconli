<?php

namespace App\Modules\RegulatoryEngine\Enums;

/** RF-121 — excepciones operativas parametrizables. */
enum ExceptionType: string
{
    case FEE_OVERRIDE = 'FEE_OVERRIDE';
    case MORA_EXEMPT = 'MORA_EXEMPT';
    case MORA_RATE_OVERRIDE = 'MORA_RATE_OVERRIDE';
    case DAYS_WITHOUT_NOVELTY = 'DAYS_WITHOUT_NOVELTY';
    case PAYMENT_EXTENSION = 'PAYMENT_EXTENSION';
    case SPECIAL_DISCOUNT = 'SPECIAL_DISCOUNT';
    case DUAL_PERIOD = 'DUAL_PERIOD';
    case CUSTOM_RULE = 'CUSTOM_RULE';
}
