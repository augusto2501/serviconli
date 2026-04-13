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
            'novelty_type_code' => [
                'required',
                'string',
                Rule::in([
                    'ING', 'TAE', 'TDE', 'TAP', 'TDP', 'VSP', 'VST', 'VTE', 'VCT', 'RET',
                    'LMA', 'LPA', 'IGE', 'IRL', 'SLN', 'LLU', 'AVP', 'COR',
                ]),
            ],
            'payer_id' => ['nullable', 'integer'],
            'start_date' => [
                'nullable', 'date',
                'required_if:novelty_type_code,LMA',
                'required_if:novelty_type_code,LPA',
                'required_if:novelty_type_code,IGE',
                'required_if:novelty_type_code,IRL',
                'required_if:novelty_type_code,SLN',
                'required_if:novelty_type_code,LLU',
            ],
            'end_date' => [
                'nullable', 'date', 'after_or_equal:start_date',
                'required_if:novelty_type_code,LMA',
                'required_if:novelty_type_code,LPA',
                'required_if:novelty_type_code,IGE',
                'required_if:novelty_type_code,IRL',
                'required_if:novelty_type_code,SLN',
                'required_if:novelty_type_code,LLU',
            ],
            'previous_entity_id' => ['nullable', 'integer'],
            'new_entity_id' => [
                'nullable',
                'integer',
                'required_if:novelty_type_code,TAE',
                'required_if:novelty_type_code,TDE',
                'required_if:novelty_type_code,TAP',
                'required_if:novelty_type_code,TDP',
            ],
            'previous_value' => ['nullable', 'integer'],
            'new_value' => [
                'nullable',
                'numeric',
                'required_if:novelty_type_code,VSP',
                'required_if:novelty_type_code,VST',
                'required_if:novelty_type_code,VTE',
                'required_if:novelty_type_code,VCT',
            ],
            'retirement_scope' => ['nullable', 'string', Rule::in(['TOTAL', 'PENSION_ONLY', 'ARL_ONLY']), 'required_if:novelty_type_code,RET'],
            'retirement_cause' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $period = new Periodo((int) $validated['period_year'], (int) $validated['period_month']);
        $type = $validated['novelty_type_code'];
        unset($validated['period_year'], $validated['period_month'], $validated['novelty_type_code']);

        $validated['created_by'] = $request->user()?->id;

        try {
            $novelty = $noveltyService->register($affiliate, $period, $type, $validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'novelty' => $novelty->load('affiliate'),
            'arl_retirement_reminder' => $noveltyService->requiresARLRetirementAlert($novelty),
        ], 201);
    }
}
