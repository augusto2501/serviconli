<?php

namespace App\Modules\Affiliates\Services;

// RF-011 — primer mes proporcional (días desde ingreso) vs. total mensual base 30 días

use App\Modules\Affiliates\Models\EnrollmentProcess;
use App\Modules\RegulatoryEngine\DTOs\CalculationInputDTO;
use App\Modules\RegulatoryEngine\Services\PILACalculationService;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Illuminate\Support\Carbon;

final class EnrollmentBillingPreviewService
{
    public function __construct(
        private readonly PILACalculationService $pila,
    ) {}

    /**
     * @return array{
     *   entryDate: string,
     *   cotizationYear: int,
     *   cotizationMonth: int,
     *   calendarDaysFromEntryToMonthEnd: int,
     *   billableDaysFirstMonth: int,
     *   monthlyFullSocialSecurityPesos: int,
     *   firstMonthProportionalSocialSecurityPesos: int,
     *   ibcRoundedPesos: int,
     * }
     */
    public function preview(EnrollmentProcess $process, int $rawIbcPesos, int $arlRiskClass = 1): array
    {
        $s1 = $process->step1_payload ?? [];
        $s4 = $process->step4_payload ?? [];

        $contributorTypeCode = (string) ($s1['contributor_type_code'] ?? '01');

        $validFrom = $s4['valid_from'] ?? null;
        $entry = $validFrom !== null
            ? Carbon::parse($validFrom)->startOfDay()
            : Carbon::now()->startOfDay();

        $period = new Periodo($entry->year, $entry->month);
        $onDate = sprintf('%04d-%02d-01', $period->year, $period->month);

        $calendarDaysFromEntryToMonthEnd = $entry->copy()->endOfMonth()->day - $entry->day + 1;
        $billableDaysFirstMonth = min($calendarDaysFromEntryToMonthEnd, 30);

        $input = new CalculationInputDTO(
            rawIbcPesos: $rawIbcPesos,
            cotizationPeriod: $period,
            contributorTypeCode: $contributorTypeCode,
            arlRiskClass: max(1, min(5, $arlRiskClass)),
        );

        $result = $this->pila->calculate($input, null, null, $onDate, 0);

        $monthlyFull = $result->totalSocialSecurityPesos;
        $firstProportional = (int) round($monthlyFull * ($billableDaysFirstMonth / 30));

        return [
            'entryDate' => $entry->toDateString(),
            'cotizationYear' => $period->year,
            'cotizationMonth' => $period->month,
            'calendarDaysFromEntryToMonthEnd' => $calendarDaysFromEntryToMonthEnd,
            'billableDaysFirstMonth' => $billableDaysFirstMonth,
            'monthlyFullSocialSecurityPesos' => $monthlyFull,
            'firstMonthProportionalSocialSecurityPesos' => $firstProportional,
            'ibcRoundedPesos' => $result->ibcRoundedPesos,
        ];
    }
}
