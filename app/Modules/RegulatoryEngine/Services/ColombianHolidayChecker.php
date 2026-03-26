<?php

namespace App\Modules\RegulatoryEngine\Services;

use App\Modules\RegulatoryEngine\Models\ColombianHoliday;
use Carbon\CarbonInterface;

final class ColombianHolidayChecker
{
    public function isHoliday(CarbonInterface $date): bool
    {
        return ColombianHoliday::query()
            ->whereDate('holiday_date', $date->toDateString())
            ->exists();
    }
}
