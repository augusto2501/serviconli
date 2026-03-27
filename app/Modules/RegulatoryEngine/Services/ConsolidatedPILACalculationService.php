<?php

namespace App\Modules\RegulatoryEngine\Services;

use App\Modules\RegulatoryEngine\DTOs\CalculationInputDTO;
use App\Modules\RegulatoryEngine\DTOs\ConsolidatedCalculationLineDTO;
use App\Modules\RegulatoryEngine\DTOs\ConsolidatedCalculationResultDTO;
use App\Modules\RegulatoryEngine\DTOs\PeriodIbcInput;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Orquesta varias liquidaciones PILA (un período por línea) y expone totales consolidados.
 * La mora por período usa la fecha límite de pago según calendario PILA y últimos dos dígitos del documento.
 */
final class ConsolidatedPILACalculationService
{
    private const MAX_PERIODS = 120;

    public function __construct(
        private readonly PILACalculationService $pila,
        private readonly PaymentCalendarService $calendar,
    ) {}

    /**
     * @param  list<PeriodIbcInput>  $periods
     */
    public function consolidate(
        array $periods,
        string $contributorTypeCode,
        int $arlRiskClass,
        string $paymentDateYmd,
        int $documentLastTwoDigits,
        ?string $targetType = null,
        ?int $targetId = null,
    ): ConsolidatedCalculationResultDTO {
        if ($periods === []) {
            throw new InvalidArgumentException('Debe indicar al menos un período.');
        }

        if (count($periods) > self::MAX_PERIODS) {
            throw new InvalidArgumentException('Demasiados períodos (máximo '.self::MAX_PERIODS.').');
        }

        $this->assertNoDuplicatePeriods($periods);

        usort($periods, static function (PeriodIbcInput $a, PeriodIbcInput $b): int {
            $ya = $a->period->year * 100 + $a->period->month;
            $yb = $b->period->year * 100 + $b->period->month;

            return $ya <=> $yb;
        });

        $payment = Carbon::parse($paymentDateYmd)->startOfDay();

        $lines = [];
        $totalSocial = 0;
        $subsystemTotals = [];

        foreach ($periods as $row) {
            $y = $row->period->year;
            $m = $row->period->month;
            $deadline = $this->calendar->paymentDateForLastTwoDigitsInMonth($documentLastTwoDigits, $y, $m)
                ->copy()
                ->startOfDay();

            $daysLate = $payment->lt($deadline) ? 0 : (int) $deadline->diffInDays($payment);

            $onDate = sprintf('%04d-%02d-01', $y, $m);

            $input = new CalculationInputDTO(
                rawIbcPesos: $row->rawIbcPesos,
                cotizationPeriod: $row->period,
                contributorTypeCode: $contributorTypeCode,
                arlRiskClass: $arlRiskClass,
            );

            $result = $this->pila->calculate(
                $input,
                $targetType,
                $targetId,
                $onDate,
                $daysLate,
            );

            $line = new ConsolidatedCalculationLineDTO(
                year: $y,
                month: $m,
                rawIbcPesos: $row->rawIbcPesos,
                daysLate: $daysLate,
                paymentDeadlineDate: $deadline->toDateString(),
                result: $result,
            );

            $lines[] = $line;
            $totalSocial += $result->totalSocialSecurityPesos;

            foreach ($result->subsystemAmountsPesos as $key => $value) {
                if (! str_ends_with((string) $key, '_pesos') || ! is_numeric($value)) {
                    continue;
                }
                $subsystemTotals[$key] = ($subsystemTotals[$key] ?? 0) + (int) round((float) $value);
            }
        }

        return new ConsolidatedCalculationResultDTO(
            lines: $lines,
            totalSocialSecurityPesos: $totalSocial,
            subsystemTotalsPesos: $subsystemTotals,
        );
    }

    /**
     * @param  list<PeriodIbcInput>  $periods
     */
    private function assertNoDuplicatePeriods(array $periods): void
    {
        $seen = [];
        foreach ($periods as $row) {
            $k = $row->period->year.'-'.$row->period->month;
            if (isset($seen[$k])) {
                throw new InvalidArgumentException("Período duplicado: {$k}.");
            }
            $seen[$k] = true;
        }
    }
}
