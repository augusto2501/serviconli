<?php

namespace App\Modules\RegulatoryEngine\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\RegulatoryEngine\DTOs\CalculationInputDTO;
use App\Modules\RegulatoryEngine\Exceptions\MissingRegulatoryParameterException;
use App\Modules\RegulatoryEngine\Services\PILACalculationService;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class PILACalculationProbeController extends Controller
{
    public function __invoke(Request $request, PILACalculationService $pila): JsonResponse
    {
        $validated = $request->validate([
            'raw_ibc_pesos' => ['required', 'integer', 'min:0'],
            'year' => ['required', 'integer', 'min:1970', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'contributor_type_code' => ['required', 'string', 'max:10'],
            'arl_risk_class' => ['required', 'integer', 'min:1', 'max:5'],
            'days_late' => ['required', 'integer', 'min:0'],
            'on_date' => ['nullable', 'date'],
            'target_type' => ['nullable', 'string', 'max:100', 'required_with:target_id'],
            'target_id' => ['nullable', 'integer', 'min:1', 'required_with:target_type'],
        ]);

        try {
            $periodo = new Periodo(
                (int) $validated['year'],
                (int) $validated['month'],
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $input = new CalculationInputDTO(
            rawIbcPesos: (int) $validated['raw_ibc_pesos'],
            cotizationPeriod: $periodo,
            contributorTypeCode: $validated['contributor_type_code'],
            arlRiskClass: (int) $validated['arl_risk_class'],
        );

        $onDate = isset($validated['on_date'])
            ? Carbon::parse($validated['on_date'])->toDateString()
            : null;
        $targetType = $validated['target_type'] ?? null;
        $targetId = isset($validated['target_id']) ? (int) $validated['target_id'] : null;

        try {
            $result = $pila->calculate(
                $input,
                $targetType,
                $targetId,
                $onDate,
                (int) $validated['days_late'],
            );
        } catch (MissingRegulatoryParameterException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ibcRoundedPesos' => $result->ibcRoundedPesos,
            'subsystemAmountsPesos' => $result->subsystemAmountsPesos,
            'totalSocialSecurityPesos' => $result->totalSocialSecurityPesos,
        ]);
    }
}
