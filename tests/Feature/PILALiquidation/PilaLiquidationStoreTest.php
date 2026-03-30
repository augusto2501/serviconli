<?php

namespace Tests\Feature\PILALiquidation;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\PILALiquidation\Models\PilaLiquidationLine;
use App\Modules\RegulatoryEngine\Models\RegulatoryParameter;
use Database\Seeders\PaymentCalendarRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilaLiquidationStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_persists_liquidation_and_lines(): void
    {
        $this->seed(PaymentCalendarRuleSeeder::class);
        $this->seedDefaultRates();

        $person = Person::query()->create([
            'document_number' => '1234567890',
            'first_name' => 'Ana',
            'first_surname' => 'Pérez',
        ]);
        $affiliate = Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ]);

        $response = $this->postJson('/api/pila/liquidations', [
            'periods' => [
                ['year' => 2026, 'month' => 1, 'raw_ibc_pesos' => 1_750_905],
                ['year' => 2026, 'month' => 2, 'raw_ibc_pesos' => 1_750_905],
            ],
            'contributor_type_code' => '01',
            'arl_risk_class' => 1,
            'payment_date' => '2026-03-15',
            'document_last_two_digits' => 0,
            'affiliate_id' => $affiliate->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'publicId',
                'status',
                'totalSocialSecurityPesos',
                'subsystemTotalsPesos',
                'lines',
                'affiliate' => ['id', 'documentNumber', 'firstName', 'lastName'],
            ]);

        $this->assertCount(2, $response->json('lines'));

        $this->assertDatabaseCount('pila_liquidations', 1);
        $this->assertDatabaseCount('pila_liquidation_lines', 2);

        $liq = PilaLiquidation::query()->first();
        $this->assertNotNull($liq);
        $this->assertSame('draft', $liq->status->value);
        $this->assertSame($affiliate->id, $liq->affiliate_id);

        $lines = PilaLiquidationLine::query()->orderBy('line_number')->get();
        $this->assertCount(2, $lines);
        $this->assertSame(1_750_905, $lines[0]->raw_ibc_pesos);
        $this->assertSame(1_751_000, $lines[0]->ibc_rounded_pesos);
        $this->assertSame(2026, $lines[0]->period_year);
        $this->assertSame(1, $lines[0]->period_month);
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
