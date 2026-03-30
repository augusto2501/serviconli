<?php

namespace Tests\Feature\PILALiquidation;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\RegulatoryEngine\Models\RegulatoryParameter;
use Database\Seeders\PaymentCalendarRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilaLiquidationShowAndStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_404_for_unknown_public_id(): void
    {
        $response = $this->getJson('/api/pila/liquidations/01ARBITRARYULID999999999999');

        $response->assertNotFound();
    }

    public function test_show_confirm_and_cancel_flow(): void
    {
        $this->seed(PaymentCalendarRuleSeeder::class);
        $this->seedDefaultRates();

        $person = Person::query()->create([
            'document_number' => '9876543210',
            'first_name' => 'Luis',
            'first_surname' => 'Gómez',
        ]);
        $affiliate = Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ]);

        $create = $this->postJson('/api/pila/liquidations', [
            'periods' => [
                ['year' => 2026, 'month' => 1, 'raw_ibc_pesos' => 1_000_000],
            ],
            'contributor_type_code' => '01',
            'arl_risk_class' => 1,
            'payment_date' => '2026-03-15',
            'document_last_two_digits' => 0,
            'affiliate_id' => $affiliate->id,
        ]);

        $create->assertCreated();
        $publicId = $create->json('publicId');
        $this->assertNotEmpty($publicId);

        $show = $this->getJson('/api/pila/liquidations/'.$publicId);
        $show->assertOk()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('affiliate.documentNumber', '9876543210');

        $confirm = $this->postJson('/api/pila/liquidations/'.$publicId.'/confirm');
        $confirm->assertOk()->assertJsonPath('status', 'confirmed');

        $cancel = $this->postJson('/api/pila/liquidations/'.$publicId.'/cancel');
        $cancel->assertOk()->assertJsonPath('status', 'cancelled');

        $secondConfirm = $this->postJson('/api/pila/liquidations/'.$publicId.'/confirm');
        $secondConfirm->assertStatus(422);

        $liq = PilaLiquidation::query()->where('public_id', $publicId)->first();
        $this->assertNotNull($liq);
        $this->assertSame('cancelled', $liq->status->value);
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
