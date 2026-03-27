<?php

namespace Tests\Feature\RegulatoryEngine;

use App\Modules\RegulatoryEngine\DTOs\PeriodIbcInput;
use App\Modules\RegulatoryEngine\Models\RegulatoryParameter;
use App\Modules\RegulatoryEngine\Repositories\RegulatoryParameterRepository;
use App\Modules\RegulatoryEngine\Services\ColombianHolidayChecker;
use App\Modules\RegulatoryEngine\Services\ConsolidatedPILACalculationService;
use App\Modules\RegulatoryEngine\Services\PaymentCalendarService;
use App\Modules\RegulatoryEngine\Services\PILACalculationService;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Database\Seeders\PaymentCalendarRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ConsolidatedPILACalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_consolidate_throws_when_empty_periods(): void
    {
        $svc = $this->makeService();

        $this->expectException(InvalidArgumentException::class);
        $svc->consolidate([], '01', 1, '2026-03-15', 0);
    }

    public function test_consolidate_throws_on_duplicate_period(): void
    {
        $svc = $this->makeService();

        $periods = [
            new PeriodIbcInput(new Periodo(2026, 1), 1_000_000),
            new PeriodIbcInput(new Periodo(2026, 1), 2_000_000),
        ];

        $this->expectException(InvalidArgumentException::class);
        $svc->consolidate($periods, '01', 1, '2026-03-15', 0);
    }

    public function test_consolidate_sums_two_periods_and_orders_lines_chronologically(): void
    {
        $this->seed(PaymentCalendarRuleSeeder::class);
        $this->seedDefaultRates();

        $svc = $this->makeService();

        $periods = [
            new PeriodIbcInput(new Periodo(2026, 2), 1_750_905),
            new PeriodIbcInput(new Periodo(2026, 1), 1_750_905),
        ];

        $result = $svc->consolidate($periods, '01', 1, '2026-03-15', 0);

        $this->assertCount(2, $result->lines);
        $this->assertSame(2026, $result->lines[0]->year);
        $this->assertSame(1, $result->lines[0]->month);
        $this->assertSame(2026, $result->lines[1]->year);
        $this->assertSame(2, $result->lines[1]->month);

        $sumLines = $result->lines[0]->result->totalSocialSecurityPesos
            + $result->lines[1]->result->totalSocialSecurityPesos;
        $this->assertSame($sumLines, $result->totalSocialSecurityPesos);
        $this->assertArrayHasKey('health_total_pesos', $result->subsystemTotalsPesos);
    }

    private function makeService(): ConsolidatedPILACalculationService
    {
        $pila = new PILACalculationService(
            operationalExceptions: null,
            regulatoryParameters: new RegulatoryParameterRepository,
        );

        return new ConsolidatedPILACalculationService(
            $pila,
            new PaymentCalendarService(new ColombianHolidayChecker),
        );
    }

    private function seedDefaultRates(): void
    {
        $params = [
            ['rates', 'SALUD_TOTAL_PERCENT', '12.5'],
            ['rates', 'PENSION_TOTAL_PERCENT', '16'],
            ['rates', 'ARL_RISK_CLASS_I_PERCENT', '0.522'],
            ['rates', 'ARL_RISK_CLASS_II_PERCENT', '1.044'],
            ['rates', 'ARL_RISK_CLASS_III_PERCENT', '2.436'],
            ['rates', 'ARL_RISK_CLASS_IV_PERCENT', '4.350'],
            ['rates', 'ARL_RISK_CLASS_V_PERCENT', '6.960'],
            ['rates', 'CCF_DEPENDIENTE_PERCENT', '4'],
            ['rates', 'CCF_INDEPENDIENTE_PERCENT', '2'],
            ['mora', 'DAILY_RATE_PERCENT', '0.0833'],
        ];

        foreach ($params as [$category, $key, $value]) {
            RegulatoryParameter::query()->create([
                'category' => $category,
                'key' => $key,
                'value' => $value,
                'data_type' => 'decimal',
                'legal_basis' => 'Test',
                'valid_from' => '2026-01-01',
                'valid_until' => null,
            ]);
        }
    }
}
