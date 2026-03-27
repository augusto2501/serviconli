<?php

namespace App\Modules\PILALiquidation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\PILALiquidation\Services\PilaLiquidationStateService;
use App\Modules\PILALiquidation\Services\StorePilaLiquidationService;
use App\Modules\RegulatoryEngine\DTOs\PeriodIbcInput;
use App\Modules\RegulatoryEngine\Exceptions\MissingRegulatoryParameterException;
use App\Modules\RegulatoryEngine\Services\ConsolidatedPILACalculationService;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class PilaLiquidationController extends Controller
{
    public function store(
        Request $request,
        ConsolidatedPILACalculationService $consolidated,
        StorePilaLiquidationService $store,
    ): JsonResponse {
        $validated = $request->validate([
            'periods' => ['required', 'array', 'min:1', 'max:120'],
            'periods.*.year' => ['required', 'integer', 'min:1970', 'max:2100'],
            'periods.*.month' => ['required', 'integer', 'min:1', 'max:12'],
            'periods.*.raw_ibc_pesos' => ['required', 'integer', 'min:0'],
            'contributor_type_code' => ['required', 'string', 'max:10'],
            'arl_risk_class' => ['required', 'integer', 'min:1', 'max:5'],
            'payment_date' => ['required', 'date'],
            'document_last_two_digits' => ['required', 'integer', 'min:0', 'max:99'],
            'target_type' => ['nullable', 'string', 'max:100', 'required_with:target_id'],
            'target_id' => ['nullable', 'integer', 'min:1', 'required_with:target_type'],
            'affiliate_id' => ['nullable', 'integer', 'exists:affiliates,id'],
        ]);

        $periods = [];
        foreach ($validated['periods'] as $row) {
            try {
                $periods[] = new PeriodIbcInput(
                    period: new Periodo((int) $row['year'], (int) $row['month']),
                    rawIbcPesos: (int) $row['raw_ibc_pesos'],
                );
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        $paymentDate = Carbon::parse($validated['payment_date'])->toDateString();
        $targetType = $validated['target_type'] ?? null;
        $targetId = isset($validated['target_id']) ? (int) $validated['target_id'] : null;
        $affiliateId = isset($validated['affiliate_id']) ? (int) $validated['affiliate_id'] : null;

        try {
            $result = $consolidated->consolidate(
                $periods,
                $validated['contributor_type_code'],
                (int) $validated['arl_risk_class'],
                $paymentDate,
                (int) $validated['document_last_two_digits'],
                $targetType,
                $targetId,
            );
        } catch (MissingRegulatoryParameterException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $liquidation = $store->store(
            $result,
            $validated['contributor_type_code'],
            (int) $validated['arl_risk_class'],
            $paymentDate,
            (int) $validated['document_last_two_digits'],
            $targetType,
            $targetId,
            $affiliateId,
        );

        return response()->json($this->liquidationToArray($liquidation->load('lines', 'affiliate')), 201);
    }

    public function show(string $publicId): JsonResponse
    {
        $liquidation = PilaLiquidation::query()
            ->where('public_id', $publicId)
            ->with(['lines', 'affiliate'])
            ->first();

        if ($liquidation === null) {
            return response()->json(['message' => 'Liquidación no encontrada.'], 404);
        }

        return response()->json($this->liquidationToArray($liquidation));
    }

    public function confirm(string $publicId, PilaLiquidationStateService $state): JsonResponse
    {
        $liquidation = PilaLiquidation::query()->where('public_id', $publicId)->first();

        if ($liquidation === null) {
            return response()->json(['message' => 'Liquidación no encontrada.'], 404);
        }

        try {
            $updated = $state->confirm($liquidation);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->liquidationToArray($updated));
    }

    public function cancel(string $publicId, PilaLiquidationStateService $state): JsonResponse
    {
        $liquidation = PilaLiquidation::query()->where('public_id', $publicId)->first();

        if ($liquidation === null) {
            return response()->json(['message' => 'Liquidación no encontrada.'], 404);
        }

        try {
            $updated = $state->cancel($liquidation);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->liquidationToArray($updated));
    }

    /** @return array<string, mixed> */
    private function liquidationToArray(PilaLiquidation $l): array
    {
        $l->loadMissing('lines', 'affiliate');

        return [
            'id' => $l->id,
            'publicId' => $l->public_id,
            'status' => $l->status->value,
            'contributorTypeCode' => $l->contributor_type_code,
            'arlRiskClass' => $l->arl_risk_class,
            'paymentDate' => $l->payment_date->toDateString(),
            'documentLastTwoDigits' => $l->document_last_two_digits,
            'targetType' => $l->target_type,
            'targetId' => $l->target_id,
            'affiliate' => $l->affiliate !== null ? [
                'id' => $l->affiliate->id,
                'documentNumber' => $l->affiliate->document_number,
                'firstName' => $l->affiliate->first_name,
                'lastName' => $l->affiliate->last_name,
            ] : null,
            'totalSocialSecurityPesos' => $l->total_social_security_pesos,
            'subsystemTotalsPesos' => $l->subsystem_totals_pesos,
            'lines' => $l->lines->map(static function ($line): array {
                return [
                    'lineNumber' => $line->line_number,
                    'periodYear' => $line->period_year,
                    'periodMonth' => $line->period_month,
                    'rawIbcPesos' => $line->raw_ibc_pesos,
                    'ibcRoundedPesos' => $line->ibc_rounded_pesos,
                    'daysLate' => $line->days_late,
                    'paymentDeadlineDate' => $line->payment_deadline_date->toDateString(),
                    'subsystemAmountsPesos' => $line->subsystem_amounts_pesos,
                    'totalSocialSecurityPesos' => $line->total_social_security_pesos,
                ];
            })->values()->all(),
            'createdAt' => $l->created_at?->toIso8601String(),
            'updatedAt' => $l->updated_at?->toIso8601String(),
        ];
    }
}
