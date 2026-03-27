<?php

namespace App\Modules\RegulatoryEngine\DTOs;

use App\Modules\RegulatoryEngine\ValueObjects\Periodo;

/** Un período de cotización con IBC bruto asociado (liquidación independiente por mes). */
final readonly class PeriodIbcInput
{
    public function __construct(
        public Periodo $period,
        public int $rawIbcPesos,
    ) {}
}
