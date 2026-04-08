<?php

namespace App\Modules\Affiliations\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliations\Services\MultiIncomeContractService;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RF-030 — contratos multi-ingreso (independientes).
 */
final class MultiIncomeContractController extends Controller
{
    public function store(
        Request $request,
        Affiliate $affiliate,
        MultiIncomeContractService $service,
    ): JsonResponse {
        $this->authorize('update', $affiliate);

        $v = $request->validate([
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'income_pesos' => ['required', 'integer', 'min:1'],
            'contract_description' => ['nullable', 'string', 'max:200'],
        ]);

        $period = new Periodo((int) $v['period_year'], (int) $v['period_month']);
        $contract = $service->addContract(
            $affiliate,
            $period,
            (int) $v['income_pesos'],
            $v['contract_description'] ?? null,
            $request->user()?->id,
        );

        return response()->json([
            'contract' => $contract,
            'consolidated_ibc_pesos' => $service->consolidatedIbc($affiliate, $period),
        ], 201);
    }
}
