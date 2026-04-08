<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\Services\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'prospect_name' => ['required', 'string', 'max:255'],
            'prospect_document' => ['nullable', 'string', 'max:64'],
            'prospect_phone' => ['nullable', 'string', 'max:64'],
            'prospect_email' => ['nullable', 'string', 'max:255'],
            'salary_pesos' => ['required', 'integer', 'min:1'],
            'contributor_type_code' => ['required', 'string', 'max:8'],
            'arl_risk_class' => ['nullable', 'integer', 'min:1', 'max:5'],
            'period_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $q = $this->quotationService->create($v, $request->user()?->id);

        return response()->json($q, 201);
    }
}
