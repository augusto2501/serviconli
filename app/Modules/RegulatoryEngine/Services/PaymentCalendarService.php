<?php

namespace App\Modules\RegulatoryEngine\Services;

use App\Modules\RegulatoryEngine\Models\PaymentCalendarRule;
use App\Modules\RegulatoryEngine\Models\PaymentDeadlineOverride;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Res. 2388/2016: últimos dos dígitos (NIT o documento) → ordinal de día hábil (2–16 en calendario estándar).
 * El mes efectivo (mes actual vs mes siguiente) lo define el llamador según dependiente/independiente.
 */
final class PaymentCalendarService
{
    public function __construct(
        private readonly ColombianHolidayChecker $holidays,
    ) {}

    /**
     * Ordinal de día hábil en el mes (1 = primer día hábil, …) según cfg_payment_calendar_rules.
     *
     * @param  int  $lastTwoDigits  Entero 0–99 (últimos dos dígitos de NIT o cédula).
     */
    public function ordinalBusinessDayFromLastTwoDigits(int $lastTwoDigits): int
    {
        if ($lastTwoDigits < 0 || $lastTwoDigits > 99) {
            throw new InvalidArgumentException('Los últimos dos dígitos deben estar entre 0 y 99.');
        }

        $rule = PaymentCalendarRule::query()
            ->where('digit_range_start', '<=', $lastTwoDigits)
            ->where('digit_range_end', '>=', $lastTwoDigits)
            ->first();

        if ($rule === null) {
            throw new InvalidArgumentException("No hay regla de calendario PILA para los últimos dos dígitos {$lastTwoDigits}.");
        }

        return (int) $rule->business_day;
    }

    /**
     * Fecha calendario del enésimo día hábil del mes (lunes–viernes, sin cfg_colombian_holidays).
     */
    public function dateOfNthBusinessDayInMonth(int $year, int $month, int $ordinal): Carbon
    {
        if ($ordinal < 1) {
            throw new InvalidArgumentException('El ordinal de día hábil debe ser >= 1.');
        }

        $cursor = Carbon::create($year, $month, 1)->startOfDay();
        $lastDay = $cursor->copy()->endOfMonth();
        $seen = 0;

        while ($cursor->lte($lastDay)) {
            if ($this->isBusinessDay($cursor)) {
                $seen++;
                if ($seen === $ordinal) {
                    return $cursor->copy();
                }
            }
            $cursor->addDay();
        }

        throw new InvalidArgumentException(
            "No existe el día hábil ordinal {$ordinal} en {$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT).'.'
        );
    }

    /**
     * Fecha de pago PILA para un mes dado según últimos dos dígitos (regla + festivos).
     */
    public function paymentDateForLastTwoDigitsInMonth(int $lastTwoDigits, int $year, int $month): Carbon
    {
        $override = $this->deadlineOverrideForPeriod($year, $month);
        if ($override !== null) {
            return Carbon::parse($override->deadline_date)->startOfDay();
        }

        $ordinal = $this->ordinalBusinessDayFromLastTwoDigits($lastTwoDigits);

        return $this->dateOfNthBusinessDayInMonth($year, $month, $ordinal);
    }

    private function deadlineOverrideForPeriod(int $year, int $month): ?PaymentDeadlineOverride
    {
        return PaymentDeadlineOverride::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();
    }

    private function isBusinessDay(CarbonInterface $date): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return ! $this->holidays->isHoliday($date);
    }
}
