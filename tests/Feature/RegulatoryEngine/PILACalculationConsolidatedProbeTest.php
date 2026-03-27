<?php

namespace Tests\Feature\RegulatoryEngine;

use App\Modules\RegulatoryEngine\Models\RegulatoryParameter;
use Database\Seeders\PaymentCalendarRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PILACalculationConsolidatedProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_consolidated_endpoint_returns_lines_and_totals(): void
    {
        $this->seed(PaymentCalendarRuleSeeder::class);
        $this->seedDefaultRates();

        $response = $this->postJson('/api/pila/calculate-consolidated', [
            'periods' => [
                ['year' => 2026, 'month' => 1, 'raw_ibc_pesos' => 1_750_905],
                ['year' => 2026, 'month' => 2, 'raw_ibc_pesos' => 1_750_905],
            ],
            'contributor_type_code' => '01',
            'arl_risk_class' => 1,
            'payment_date' => '2026-03-15',
            'document_last_two_digits' => 0,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'lines' => [
                    '*' => [
                        'year',
                        'month',
                        'daysLate',
                        'paymentDeadlineDate',
                        'ibcRoundedPesos',
                        'subsystemAmountsPesos',
                        'totalSocialSecurityPesos',
                    ],
                ],
                'totalSocialSecurityPesos',
                'subsystemTotalsPesos',
            ]);

        $this->assertCount(2, $response->json('lines'));
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
