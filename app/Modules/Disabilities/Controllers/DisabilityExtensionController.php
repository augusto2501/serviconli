<?php

namespace App\Modules\Disabilities\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Disabilities\Models\AffiliateDisability;
use App\Modules\Disabilities\Models\DisabilityExtension;
use App\Modules\Disabilities\Services\DisabilityDayCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DisabilityExtensionController extends Controller
{
    public function __construct(
        private readonly DisabilityDayCalculator $dayCalculator,
    ) {}

    public function store(Request $request, Affiliate $affiliate, AffiliateDisability $disability): JsonResponse
    {
        $this->authorize('update', $affiliate);
        $this->assertSameAffiliate($affiliate, $disability);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $ext = DisabilityExtension::query()->create([
            ...$validated,
            'disability_id' => $disability->id,
        ]);

        $this->dayCalculator->recalculate($disability->fresh());

        return response()->json([
            'id' => $ext->id,
            'disabilityId' => $disability->id,
            'startDate' => $ext->start_date?->toDateString(),
            'endDate' => $ext->end_date?->toDateString(),
            'notes' => $ext->notes,
            'cumulativeDays' => (int) $disability->fresh()->cumulative_days,
            'over180Alert' => (bool) $disability->fresh()->over_180_alert,
        ], 201);
    }

    private function assertSameAffiliate(Affiliate $affiliate, AffiliateDisability $disability): void
    {
        if ((int) $disability->affiliate_id !== (int) $affiliate->id) {
            abort(404);
        }
    }
}
