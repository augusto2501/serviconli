<?php

namespace Tests\Feature\RegulatoryEngine;

use App\Modules\RegulatoryEngine\Models\RegulatoryParameter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PILACalculationProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_fails_when_required_fields_missing(): void
    {
        $response = $this->getJson('/api/pila/calculate');

        $response->assertStatus(422);
    }

    public function test_target_id_requires_target_type(): void
    {
        $response = $this->getJson('/api/pila/calculate?'.http_build_query([
            'raw_ibc_pesos' => 1000000,
            'year' => 2026,
            'month' => 3,
            'contributor_type_code' => '01',
            'arl_risk_class' => 1,
            'days_late' => 0,
            'target_id' => 1,
        ]));

        $response->assertStatus(422);
    }

    public function test_returns_calculation_json_when_parameters_exist(): void
    {
        $this->seedDefaultRates();

        $response = $this->getJson('/api/pila/calculate?'.http_build_query([
            'raw_ibc_pesos' => 1_750_905,
            'year' => 2026,
            'month' => 3,
            'contributor_type_code' => '01',
            'arl_risk_class' => 1,
            'days_late' => 0,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'ibcRoundedPesos',
                'subsystemAmountsPesos',
                'totalSocialSecurityPesos',
            ]);

        $response->assertJsonPath('ibcRoundedPesos', 1_751_000);
    }

    public function test_missing_regulatory_parameter_returns_422_message(): void
    {
        $response = $this->getJson('/api/pila/calculate?'.http_build_query([
            'raw_ibc_pesos' => 1000000,
            'year' => 2026,
            'month' => 3,
            'contributor_type_code' => '01',
            'arl_risk_class' => 1,
            'days_late' => 0,
        ]));

        $response->assertStatus(422)
            ->assertJsonStructure(['message']);
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
