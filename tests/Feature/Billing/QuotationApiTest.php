<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Models\Quotation;
use App\Modules\RegulatoryEngine\Models\RegulatoryParameter;
use Database\Seeders\PaymentCalendarRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_quotation_uses_pila_formulas(): void
    {
        $this->seed(PaymentCalendarRuleSeeder::class);
        $this->seedDefaultRates();

        $r = $this->postJson('/api/quotations', [
            'prospect_name' => 'Cliente Demo',
            'salary_pesos' => 1_750_905,
            'contributor_type_code' => '01',
            'arl_risk_class' => 1,
            'period_year' => 2026,
            'period_month' => 1,
        ]);

        $r->assertCreated()
            ->assertJsonPath('prospect_name', 'Cliente Demo')
            ->assertJsonPath('salary_pesos', 1_750_905);

        $this->assertSame(1, Quotation::query()->count());
        $amounts = $r->json('amounts');
        $this->assertIsArray($amounts);
        $this->assertArrayHasKey('totalSocialSecurityPesos', $amounts);
        $this->assertGreaterThan(0, (int) $amounts['totalSocialSecurityPesos']);
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
