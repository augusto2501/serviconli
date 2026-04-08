<?php

namespace App\Modules\Disabilities\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Disabilities\Models\AffiliateDisability;
use App\Modules\Disabilities\Services\DisabilityDayCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AffiliateDisabilityController extends Controller
{
    public function __construct(
        private readonly DisabilityDayCalculator $dayCalculator,
    ) {}

    public function index(Affiliate $affiliate): JsonResponse
    {
        $this->authorize('view', $affiliate);

        $rows = AffiliateDisability::query()
            ->where('affiliate_id', $affiliate->id)
            ->with(['diagnosisCie10', 'extensions'])
            ->orderByDesc('start_date')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (AffiliateDisability $d): array => $this->toArray($d))->all(),
        ]);
    }

    public function store(Request $request, Affiliate $affiliate): JsonResponse
    {
        $this->authorize('update', $affiliate);

        $validated = $request->validate([
            'source' => ['required', 'string', Rule::in(['EPS_GENERAL', 'ARL_LABOR'])],
            'subtype_code' => ['required', 'string', 'max:64'],
            'diagnosis_cie10_id' => ['required', 'integer', 'exists:cfg_diagnosis_cie10,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'submitted_documents' => ['nullable', 'array'],
            'submitted_documents.*' => ['string', 'max:64'],
        ]);

        $d = AffiliateDisability::query()->create([
            ...$validated,
            'affiliate_id' => $affiliate->id,
            'created_by' => $request->user()?->id,
        ]);

        $this->dayCalculator->recalculate($d->fresh());

        return response()->json($this->toArray($d->fresh(['diagnosisCie10', 'extensions'])), 201);
    }

    public function show(Affiliate $affiliate, AffiliateDisability $disability): JsonResponse
    {
        $this->authorize('view', $affiliate);
        $this->assertSameAffiliate($affiliate, $disability);

        return response()->json($this->toArray($disability->load(['diagnosisCie10', 'extensions'])));
    }

    public function update(Request $request, Affiliate $affiliate, AffiliateDisability $disability): JsonResponse
    {
        $this->authorize('update', $affiliate);
        $this->assertSameAffiliate($affiliate, $disability);

        $validated = $request->validate([
            'subtype_code' => ['sometimes', 'string', 'max:64'],
            'diagnosis_cie10_id' => ['sometimes', 'integer', 'exists:cfg_diagnosis_cie10,id'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
            'submitted_documents' => ['nullable', 'array'],
            'submitted_documents.*' => ['string', 'max:64'],
        ]);

        $disability->fill($validated);
        $disability->save();

        $this->dayCalculator->recalculate($disability->fresh());

        return response()->json($this->toArray($disability->fresh(['diagnosisCie10', 'extensions'])));
    }

    public function destroy(Affiliate $affiliate, AffiliateDisability $disability): JsonResponse
    {
        $this->authorize('update', $affiliate);
        $this->assertSameAffiliate($affiliate, $disability);

        $disability->delete();

        return response()->json(null, 204);
    }

    private function assertSameAffiliate(Affiliate $affiliate, AffiliateDisability $disability): void
    {
        if ((int) $disability->affiliate_id !== (int) $affiliate->id) {
            abort(404);
        }
    }

    /** @return array<string, mixed> */
    private function toArray(AffiliateDisability $d): array
    {
        return [
            'id' => $d->id,
            'affiliateId' => $d->affiliate_id,
            'source' => $d->source,
            'subtypeCode' => $d->subtype_code,
            'diagnosisCie10Id' => $d->diagnosis_cie10_id,
            'diagnosis' => $d->diagnosisCie10 ? [
                'code' => $d->diagnosisCie10->code,
                'description' => $d->diagnosisCie10->description,
            ] : null,
            'startDate' => $d->start_date?->toDateString(),
            'endDate' => $d->end_date?->toDateString(),
            'submittedDocuments' => $d->submitted_documents ?? [],
            'cumulativeDays' => (int) $d->cumulative_days,
            'over180Alert' => (bool) $d->over_180_alert,
            'extensions' => $d->relationLoaded('extensions')
                ? $d->extensions->map(fn ($e): array => [
                    'id' => $e->id,
                    'startDate' => $e->start_date?->toDateString(),
                    'endDate' => $e->end_date?->toDateString(),
                    'notes' => $e->notes,
                ])->all()
                : [],
        ];
    }
}
