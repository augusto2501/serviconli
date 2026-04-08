<?php

namespace Tests\Feature\Affiliates;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\PILALiquidation\Enums\PilaLiquidationStatus;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\PILALiquidation\Models\PilaLiquidationLine;
use App\Modules\RegulatoryEngine\Models\AffiliateStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MoraTransicionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_transicion_dry_run_counts_unpaid_activo(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');

        $this->artisan('pila:transicion-periodo', ['periodo' => '2026-01', '--dry-run' => true])
            ->expectsOutputToContain('2026-01')
            ->assertSuccessful();

        $affiliate->refresh();
        $this->assertSame('ACTIVO', $affiliate->status->code);
    }

    public function test_transicion_escalates_unpaid_activo(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');

        $this->artisan('pila:transicion-periodo', ['periodo' => '2026-01'])
            ->assertSuccessful();

        $affiliate->refresh();
        $this->assertSame('SUSPENDIDO', $affiliate->status->code);
    }

    public function test_transicion_skips_when_pila_confirmed_for_period(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');
        $this->seedConfirmedLiquidationLine($affiliate, 2026, 1);

        $this->artisan('pila:transicion-periodo', ['periodo' => '2026-01'])
            ->assertSuccessful();

        $affiliate->refresh();
        $this->assertSame('ACTIVO', $affiliate->status->code);
    }

    public function test_mora_detect_lists_missing_payment(): void
    {
        $this->makeAffiliate('ACTIVO');

        $this->artisan('mora:detect', ['--periodo' => '2026-01'])
            ->expectsOutputToContain('sin PILA confirmada')
            ->assertSuccessful();
    }

    private function makeAffiliate(string $statusCode): Affiliate
    {
        $statusId = AffiliateStatus::query()->where('code', $statusCode)->value('id');
        $this->assertNotNull($statusId, 'Migraciones deben sembrar cfg_affiliate_statuses.');

        $person = Person::query()->create([
            'document_type' => 'CC',
            'document_number' => (string) random_int(100000000, 999999999),
            'first_name' => 'T',
            'first_surname' => 'Mora',
        ]);

        return Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
            'status_id' => $statusId,
            'mora_status' => 'AL_DIA',
        ]);
    }

    private function seedConfirmedLiquidationLine(Affiliate $affiliate, int $year, int $month): void
    {
        $liq = PilaLiquidation::query()->create([
            'public_id' => (string) Str::ulid(),
            'status' => PilaLiquidationStatus::Confirmed,
            'contributor_type_code' => '01',
            'arl_risk_class' => 1,
            'payment_date' => sprintf('%04d-%02d-10', $year, $month),
            'document_last_two_digits' => 0,
            'affiliate_id' => $affiliate->id,
            'total_social_security_pesos' => 100_000,
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
            'total_social_security_pesos' => 100_000,
        ]);
    }
}
