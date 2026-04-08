<?php

namespace App\Modules\Disabilities\Services;

use App\Modules\Disabilities\Models\AffiliateDisability;
use App\Modules\Disabilities\Models\DisabilityExtension;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Días corridos inclusivos + prórrogas; alerta &gt; 180 (RF-098).
 */
final class DisabilityDayCalculator
{
    public function recalculate(AffiliateDisability $disability): void
    {
        $base = $this->baseSegmentDays($disability);
        $extra = (int) DisabilityExtension::query()
            ->where('disability_id', $disability->id)
            ->get()
            ->sum(fn (DisabilityExtension $e): int => $this->inclusiveDays($e->start_date, $e->end_date));

        $total = $base + $extra;
        $disability->cumulative_days = $total;
        $disability->over_180_alert = $total > 180;
        $disability->saveQuietly();
    }

    private function baseSegmentDays(AffiliateDisability $disability): int
    {
        $start = CarbonImmutable::parse($disability->start_date)->startOfDay();
        if ($disability->end_date === null) {
            $end = CarbonImmutable::now()->startOfDay();
        } else {
            $end = CarbonImmutable::parse($disability->end_date)->startOfDay();
        }

        if ($end->lt($start)) {
            return 0;
        }

        return $this->inclusiveDays($start, $end);
    }

    private function inclusiveDays(Carbon|CarbonImmutable $start, Carbon|CarbonImmutable $end): int
    {
        $a = $start instanceof CarbonImmutable ? $start : CarbonImmutable::parse($start)->startOfDay();
        $b = $end instanceof CarbonImmutable ? $end : CarbonImmutable::parse($end)->startOfDay();

        return (int) $a->diffInDays($b) + 1;
    }
}
