<?php

namespace App\Modules\RegulatoryEngine\DTOs;

/** Una línea liquidada por período (fecha límite de pago + resultado PILA). */
final readonly class ConsolidatedCalculationLineDTO
{
    public function __construct(
        public int $year,
        public int $month,
        public int $rawIbcPesos,
        public int $daysLate,
        public string $paymentDeadlineDate,
        public CalculationResultDTO $result,
    ) {}
}
