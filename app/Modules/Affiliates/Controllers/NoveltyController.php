<?php

namespace App\Modules\Affiliates\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Services\NoveltyService;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class NoveltyController extends Controller
{
    public function store(Request $request, Affiliate $affiliate, NoveltyService $noveltyService): JsonResponse
    {
        $this->authorize('update', $affiliate);

        $validated = $request->validate([
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'novelty_type_code' => ['required', 'string', Rule::in(['TAE', 'TAP', 'VSP', 'VST', 'RET'])],
            'payer_id' => ['nullable', 'integer'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'previous_entity_id' => ['nullable', 'integer'],
            'new_entity_id' => ['nullable', 'integer', 'required_if:novelty_type_code,TAE', 'required_if:novelty_type_code,TAP'],
            'previous_value' => ['nullable', 'integer'],
            'new_value' => ['nullable', 'integer', 'required_if:novelty_type_code,VSP', 'required_if:novelty_type_code,VST'],
            'retirement_scope' => ['nullable', 'string', Rule::in(['TOTAL', 'PENSION_ONLY', 'ARL_ONLY']), 'required_if:novelty_type_code,RET'],
            'retirement_cause' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $period = new Periodo((int) $validated['period_year'], (int) $validated['period_month']);
        $type = $validated['novelty_type_code'];
        unset($validated['period_year'], $validated['period_month'], $validated['novelty_type_code']);

        $validated['created_by'] = $request->user()?->id;

        $novelty = $noveltyService->register($affiliate, $period, $type, $validated);

        return response()->json([
            'novelty' => $novelty->load('affiliate'),
            'arl_retirement_reminder' => $noveltyService->requiresARLRetirementAlert($novelty),
        ], 201);
    }
}
