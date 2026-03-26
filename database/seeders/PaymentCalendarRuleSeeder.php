<?php

namespace Database\Seeders;

use App\Modules\RegulatoryEngine\Models\PaymentCalendarRule;
use Illuminate\Database\Seeder;

/** Res. 2388/2016 — 16 rangos de últimos dos dígitos → día hábil de pago. */
class PaymentCalendarRuleSeeder extends Seeder
{
    public function run(): void
    {
        $ranges = [
            [0, 7, 2],
            [8, 14, 3],
            [15, 21, 4],
            [22, 28, 5],
            [29, 35, 6],
            [36, 42, 7],
            [43, 49, 8],
            [50, 56, 9],
            [57, 63, 10],
            [64, 69, 11],
            [70, 75, 12],
            [76, 81, 13],
            [82, 87, 14],
            [88, 93, 15],
            [94, 99, 16],
        ];

        foreach ($ranges as [$start, $end, $day]) {
            PaymentCalendarRule::query()->updateOrCreate(
                [
                    'digit_range_start' => $start,
                    'digit_range_end' => $end,
                ],
                ['business_day' => $day]
            );
        }
    }
}
