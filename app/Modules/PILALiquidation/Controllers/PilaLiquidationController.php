<?php

namespace App\Modules\PILALiquidation\Controllers;

use App\Http\Controllers\Controller;
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
            'subject_type' => ['nullable', 'string', 'max:100', 'required_with:subject_id'],
            'subject_id' => ['nullable', 'integer', 'min:1', 'required_with:subject_type'],
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
        $subjectType = $validated['subject_type'] ?? null;
        $subjectId = isset($validated['subject_id']) ? (int) $validated['subject_id'] : null;

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
            $subjectType,
            $subjectId,
        );

        return response()->json([
            'id' => $liquidation->id,
            'publicId' => $liquidation->public_id,
            'status' => $liquidation->status->value,
            'totalSocialSecurityPesos' => $liquidation->total_social_security_pesos,
            'subsystemTotalsPesos' => $liquidation->subsystem_totals_pesos,
            'lineCount' => $liquidation->lines->count(),
        ], 201);
    }
}
