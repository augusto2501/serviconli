<?php

namespace App\Modules\PILALiquidation\Services;

use App\Modules\PILALiquidation\Enums\PilaLiquidationStatus;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\PILALiquidation\Models\PilaLiquidationLine;
use App\Modules\RegulatoryEngine\DTOs\ConsolidatedCalculationResultDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StorePilaLiquidationService
{
    public function store(
        ConsolidatedCalculationResultDTO $result,
        string $contributorTypeCode,
        int $arlRiskClass,
        string $paymentDateYmd,
        int $documentLastTwoDigits,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
    ): PilaLiquidation {
        return DB::transaction(function () use (
            $result,
            $contributorTypeCode,
            $arlRiskClass,
            $paymentDateYmd,
            $documentLastTwoDigits,
            $targetType,
            $targetId,
            $subjectType,
            $subjectId,
        ): PilaLiquidation {
            $liquidation = PilaLiquidation::query()->create([
                'public_id' => (string) Str::ulid(),
                'status' => PilaLiquidationStatus::Draft,
                'contributor_type_code' => $contributorTypeCode,
                'arl_risk_class' => $arlRiskClass,
                'payment_date' => $paymentDateYmd,
                'document_last_two_digits' => $documentLastTwoDigits,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'total_social_security_pesos' => $result->totalSocialSecurityPesos,
                'subsystem_totals_pesos' => $result->subsystemTotalsPesos,
            ]);

            $n = 1;
            foreach ($result->lines as $line) {
                PilaLiquidationLine::query()->create([
                    'pila_liquidation_id' => $liquidation->id,
                    'line_number' => $n,
                    'period_year' => $line->year,
                    'period_month' => $line->month,
                    'raw_ibc_pesos' => $line->rawIbcPesos,
                    'ibc_rounded_pesos' => $line->result->ibcRoundedPesos,
                    'days_late' => $line->daysLate,
                    'payment_deadline_date' => $line->paymentDeadlineDate,
                    'subsystem_amounts_pesos' => $line->result->subsystemAmountsPesos,
                    'total_social_security_pesos' => $line->result->totalSocialSecurityPesos,
                ]);
                $n++;
            }

            return $liquidation->load('lines');
        });
    }
}
