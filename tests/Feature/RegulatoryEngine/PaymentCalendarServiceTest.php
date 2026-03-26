<?php

namespace Tests\Feature\RegulatoryEngine;

use App\Modules\RegulatoryEngine\Models\ColombianHoliday;
use App\Modules\RegulatoryEngine\Services\ColombianHolidayChecker;
use App\Modules\RegulatoryEngine\Services\PaymentCalendarService;
use Database\Seeders\PaymentCalendarRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PaymentCalendarService
    {
        return new PaymentCalendarService(new ColombianHolidayChecker);
    }

    public function test_ordinal_from_last_two_digits_matches_res_2388_ranges(): void
    {
        $this->seed(PaymentCalendarRuleSeeder::class);

        $svc = $this->service();

        $this->assertSame(2, $svc->ordinalBusinessDayFromLastTwoDigits(0));
        $this->assertSame(2, $svc->ordinalBusinessDayFromLastTwoDigits(7));
        $this->assertSame(9, $svc->ordinalBusinessDayFromLastTwoDigits(52));
        $this->assertSame(16, $svc->ordinalBusinessDayFromLastTwoDigits(99));
    }

    public function test_nth_business_day_skips_weekend_and_holiday(): void
    {
        ColombianHoliday::query()->create([
            'holiday_date' => '2026-01-01',
            'name' => 'Año Nuevo',
            'law_basis' => 'Test',
        ]);

        $svc = $this->service();

        // Ene 2026: 1 ene jueves festivo; 2 vie = 1.er hábil; 5 lun = 2.º hábil
        $this->assertSame(
            '2026-01-05',
            $svc->dateOfNthBusinessDayInMonth(2026, 1, 2)->toDateString()
        );
    }

    public function test_payment_date_combines_rule_and_calendar(): void
    {
        $this->seed(PaymentCalendarRuleSeeder::class);
        ColombianHoliday::query()->create([
            'holiday_date' => '2026-01-01',
            'name' => 'Año Nuevo',
            'law_basis' => 'Test',
        ]);

        $svc = $this->service();

        // Dígitos 00–07 → ordinal 2 → 2026-01-05
        $this->assertSame(
            '2026-01-05',
            $svc->paymentDateForLastTwoDigitsInMonth(0, 2026, 1)->toDateString()
        );
    }

    public function test_invalid_last_two_digits_throws(): void
    {
        $this->seed(PaymentCalendarRuleSeeder::class);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->ordinalBusinessDayFromLastTwoDigits(100);
    }
}
