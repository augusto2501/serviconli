<?php

namespace Tests\Feature\Affiliates;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Events\ARLRetirementReminderRequested;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\PILALiquidation\Enums\PilaLiquidationStatus;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\PILALiquidation\Models\PilaLiquidationLine;
use App\Modules\RegulatoryEngine\Models\AffiliateStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class SprintGNoveltyCertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_certificate_unpaid_period(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');

        $r = $this->getJson('/api/affiliates/'.$affiliate->id.'/payment-certificate?year=2026&month=1');

        $r->assertOk()
            ->assertJsonPath('paid', false)
            ->assertJsonPath('period.year', 2026)
            ->assertJsonPath('period.month', 1);
    }

    public function test_payment_certificate_paid_when_liquidation_confirmed(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');
        $this->seedLine($affiliate, 2026, 1);

        $r = $this->getJson('/api/affiliates/'.$affiliate->id.'/payment-certificate?year=2026&month=1');

        $r->assertOk()
            ->assertJsonPath('paid', true)
            ->assertJsonStructure(['line' => ['ibcRoundedPesos', 'totalSocialSecurityPesos']]);
    }

    public function test_novelty_ret_total_dispatches_arl_reminder_and_retires(): void
    {
        Event::fake([ARLRetirementReminderRequested::class]);

        $affiliate = $this->makeAffiliate('ACTIVO');

        $r = $this->postJson('/api/affiliates/'.$affiliate->id.'/novelties', [
            'period_year' => 2026,
            'period_month' => 2,
            'novelty_type_code' => 'RET',
            'retirement_scope' => 'TOTAL',
        ]);

        $r->assertCreated()
            ->assertJsonPath('arl_retirement_reminder', true);

        Event::assertDispatched(ARLRetirementReminderRequested::class);

        $affiliate->refresh();
        $this->assertSame('RETIRADO', $affiliate->status->code);
    }

    public function test_novelty_vsp_versions_profile(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');

        $r = $this->postJson('/api/affiliates/'.$affiliate->id.'/novelties', [
            'period_year' => 2026,
            'period_month' => 3,
            'novelty_type_code' => 'VSP',
            'new_value' => 2_000_000,
        ]);

        $r->assertCreated();

        $this->assertDatabaseHas('afl_social_security_profiles', [
            'affiliate_id' => $affiliate->id,
            'ibc' => 2_000_000,
        ]);
    }

    private function makeAffiliate(string $statusCode): Affiliate
    {
        $statusId = AffiliateStatus::query()->where('code', $statusCode)->value('id');
        $this->assertNotNull($statusId);

        $person = Person::query()->create([
            'document_type' => 'CC',
            'document_number' => (string) random_int(100000000, 999999999),
            'first_name' => 'N',
            'first_surname' => 'Cert',
        ]);

        return Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
            'status_id' => $statusId,
            'mora_status' => 'AL_DIA',
        ]);
    }

    private function seedLine(Affiliate $affiliate, int $year, int $month): void
    {
        $liq = PilaLiquidation::query()->create([
            'public_id' => (string) Str::ulid(),
            'status' => PilaLiquidationStatus::Confirmed,
            'contributor_type_code' => '01',
            'arl_risk_class' => 1,
            'payment_date' => sprintf('%04d-%02d-10', $year, $month),
            'document_last_two_digits' => 0,
            'affiliate_id' => $affiliate->id,
            'total_social_security_pesos' => 50_000,
            'subsystem_totals_pesos' => [],
        ]);

        PilaLiquidationLine::query()->create([
            'pila_liquidation_id' => $liq->id,
            'line_number' => 1,
            'period_year' => $year,
            'period_month' => $month,
            'raw_ibc_pesos' => 1_000_000,
            'ibc_rounded_pesos' => 1_000_000,
            'days_late' => 0,
            'payment_deadline_date' => sprintf('%04d-%02d-20', $year, $month),
            'subsystem_amounts_pesos' => [],
            'total_social_security_pesos' => 50_000,
        ]);
    }
}
