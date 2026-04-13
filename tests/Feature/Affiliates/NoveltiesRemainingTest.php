<?php

namespace Tests\Feature\Affiliates;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Affiliates\Services\NoveltyService;
use App\Modules\Affiliations\Models\SocialSecurityProfile;
use App\Modules\RegulatoryEngine\Models\AffiliateStatus;
use App\Modules\RegulatoryEngine\Models\SSEntity;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * RF-061 — 13 tipos de novedad PILA restantes.
 *
 * @see DOCUMENTO_RECTOR §3.4
 */
class NoveltiesRemainingTest extends TestCase
{
    use RefreshDatabase;

    private NoveltyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NoveltyService::class);
    }

    // ─── ING ──────────────────────────────────────────────────────────────────

    public function test_ing_activates_affiliate_when_status_is_afiliado(): void
    {
        $affiliate = $this->makeAffiliate('AFILIADO');
        $period = new Periodo(2026, 4);

        $this->service->register($affiliate, $period, 'ING', [
            'start_date' => '2026-04-10',
        ]);

        $affiliate->refresh();
        $this->assertSame('ACTIVO', $affiliate->status->code);
    }

    public function test_ing_leaves_activo_unchanged(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');
        $period = new Periodo(2026, 4);

        $this->service->register($affiliate, $period, 'ING', [
            'start_date' => '2026-04-10',
        ]);

        $affiliate->refresh();
        $this->assertSame('ACTIVO', $affiliate->status->code);
    }

    // ─── TDE / TDP ────────────────────────────────────────────────────────────

    public function test_tde_versions_profile_for_eps_entity(): void
    {
        [$epsA, $epsB] = $this->makeEntities('EPS', 2);
        $affiliate = $this->makeAffiliate('ACTIVO');
        $this->seedProfile($affiliate, eps_entity_id: $epsA->id, afp_entity_id: null);
        $period = new Periodo(2026, 4);

        $this->service->register($affiliate, $period, 'TDE', [
            'new_entity_id' => $epsB->id,
        ]);

        $profiles = SocialSecurityProfile::query()
            ->where('affiliate_id', $affiliate->id)
            ->orderByDesc('valid_from')
            ->get();

        $this->assertCount(2, $profiles);
        $this->assertSame($epsB->id, $profiles->first()->eps_entity_id);
    }

    public function test_tdp_versions_profile_for_afp_entity(): void
    {
        [$afpA, $afpB] = $this->makeEntities('AFP', 2);
        $affiliate = $this->makeAffiliate('ACTIVO');
        $this->seedProfile($affiliate, eps_entity_id: null, afp_entity_id: $afpA->id);
        $period = new Periodo(2026, 4);

        $this->service->register($affiliate, $period, 'TDP', [
            'new_entity_id' => $afpB->id,
        ]);

        $profiles = SocialSecurityProfile::query()
            ->where('affiliate_id', $affiliate->id)
            ->orderByDesc('valid_from')
            ->get();

        $this->assertCount(2, $profiles);
        $this->assertSame($afpB->id, $profiles->first()->afp_entity_id);
    }

    // ─── VTE ──────────────────────────────────────────────────────────────────

    public function test_vte_versions_profile_with_new_eps_tariff(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');
        $this->seedProfile($affiliate);
        $period = new Periodo(2026, 4);

        // RF-061: VTE registra nueva tarifa EPS y versiona perfil
        $this->service->register($affiliate, $period, 'VTE', [
            'new_value' => '0.1250',
        ]);

        $profiles = SocialSecurityProfile::query()
            ->where('affiliate_id', $affiliate->id)
            ->orderByDesc('valid_from')
            ->get();

        $this->assertCount(2, $profiles);
        $this->assertEquals(0.1250, $profiles->first()->eps_tarifa);
    }

    // ─── VCT ──────────────────────────────────────────────────────────────────

    public function test_vct_versions_profile_with_new_arl_risk_class(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');
        $this->seedProfile($affiliate, arl_risk_class: 1);
        $period = new Periodo(2026, 4);

        // RF-061: VCT cambia clase de riesgo ARL y versiona perfil
        $this->service->register($affiliate, $period, 'VCT', [
            'new_value' => '3',
        ]);

        $profiles = SocialSecurityProfile::query()
            ->where('affiliate_id', $affiliate->id)
            ->orderByDesc('valid_from')
            ->get();

        $this->assertCount(2, $profiles);
        $this->assertSame(3, $profiles->first()->arl_risk_class);
    }

    // ─── Tipos sin efecto en perfil (solo registro en afl_novelties) ──────────

    #[DataProvider('calculationOnlyTypesProvider')]
    public function test_calculation_only_novelties_do_not_version_profile(string $code): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');
        $this->seedProfile($affiliate);
        $period = new Periodo(2026, 4);

        $this->service->register($affiliate, $period, $code, [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]);

        $this->assertCount(
            1,
            SocialSecurityProfile::query()->where('affiliate_id', $affiliate->id)->get(),
            "El tipo {$code} no debe versionar el perfil SS",
        );
    }

    public static function calculationOnlyTypesProvider(): array
    {
        return [
            'LMA' => ['LMA'],
            'LPA' => ['LPA'],
            'IGE' => ['IGE'],
            'IRL' => ['IRL'],
            'SLN' => ['SLN'],
            'LLU' => ['LLU'],
            'AVP' => ['AVP'],
            'COR' => ['COR'],
        ];
    }

    // ─── Validación de combinaciones ──────────────────────────────────────────

    public function test_ing_plus_tae_is_invalid_combination(): void
    {
        // RF-061: No se puede trasladar EPS en el mismo período de ingreso
        [$eps] = $this->makeEntities('EPS', 1);
        $affiliate = $this->makeAffiliate('AFILIADO');
        $this->seedProfile($affiliate, eps_entity_id: $eps->id);
        $period = new Periodo(2026, 4);

        $this->service->register($affiliate, $period, 'ING', [
            'start_date' => '2026-04-10',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->register($affiliate, $period, 'TAE', [
            'new_entity_id' => $eps->id,
        ]);
    }

    public function test_ing_plus_tde_is_invalid_combination(): void
    {
        // RF-061: TDE tampoco puede combinarse con ING en el mismo período
        [$eps] = $this->makeEntities('EPS', 1);
        $affiliate = $this->makeAffiliate('AFILIADO');
        $this->seedProfile($affiliate, eps_entity_id: $eps->id);
        $period = new Periodo(2026, 4);

        $this->service->register($affiliate, $period, 'ING', [
            'start_date' => '2026-04-10',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->register($affiliate, $period, 'TDE', [
            'new_entity_id' => $eps->id,
        ]);
    }

    public function test_ige_plus_irl_is_invalid_combination(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');
        $this->seedProfile($affiliate);
        $period = new Periodo(2026, 4);

        $this->service->register($affiliate, $period, 'IGE', [
            'start_date' => '2026-04-01', 'end_date' => '2026-04-10',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->register($affiliate, $period, 'IRL', [
            'start_date' => '2026-04-01', 'end_date' => '2026-04-10',
        ]);
    }

    public function test_ret_plus_ing_is_invalid_combination(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');
        $this->seedProfile($affiliate);
        $period = new Periodo(2026, 4);

        $this->service->register($affiliate, $period, 'RET', [
            'retirement_scope' => 'TOTAL', 'retirement_cause' => 'VOLUNTARIO',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->register($affiliate, $period, 'ING');
    }

    /** RF-063: Retiro por mora — provisiona deuda y pasa a RETIRADO. */
    public function test_mora_retirement_provisions_debt_and_retires(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');
        $this->seedProfile($affiliate);
        $period = new Periodo(2026, 4);

        // Simular liquidación pendiente sin pagar
        \App\Modules\PILALiquidation\Models\PilaLiquidation::query()->create([
            'affiliate_id' => $affiliate->id,
            'public_id' => 'PL-MORA-001',
            'contributor_type_code' => '03',
            'arl_risk_class' => 1,
            'payment_date' => now(),
            'document_last_two_digits' => 1,
            'total_social_security_pesos' => 250000,
            'subsystem_totals_pesos' => json_encode(['health' => 250000]),
            'status' => 'confirmed',
        ]);

        $novelty = $this->service->register($affiliate, $period, 'RET', [
            'retirement_scope' => 'TOTAL',
            'retirement_cause' => 'MORA_EN_APORTE',
        ]);

        $affiliate->refresh();
        $this->assertSame('RETIRADO', $affiliate->status->code);
        $this->assertStringContainsString('RF-063', $novelty->fresh()->notes);

        $this->assertDatabaseHas('bill_accounts_receivable', [
            'affiliate_id' => $affiliate->id,
            'concept' => 'DEUDA_MORA_RETIRO',
            'amount_pesos' => 250000,
        ]);
    }

    public function test_ige_can_combine_with_any_novelty(): void
    {
        // RF-061: IGE puede coexistir con cualquier otra novedad
        $affiliate = $this->makeAffiliate('ACTIVO');
        $this->seedProfile($affiliate);
        $period = new Periodo(2026, 4);

        $this->service->register($affiliate, $period, 'IGE', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-10',
        ]);

        $this->service->register($affiliate, $period, 'VSP', [
            'new_value' => '1500000',
        ]);

        $this->assertCount(2, $this->service->forPeriod($affiliate, $period));
    }

    // ─── Controller validation ─────────────────────────────────────────────

    public function test_lma_requires_start_and_end_date(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');

        $r = $this->postJson("/api/affiliates/{$affiliate->id}/novelties", [
            'period_year' => 2026, 'period_month' => 4,
            'novelty_type_code' => 'LMA',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['start_date', 'end_date']);
    }

    public function test_ige_requires_start_and_end_date(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');

        $r = $this->postJson("/api/affiliates/{$affiliate->id}/novelties", [
            'period_year' => 2026, 'period_month' => 4,
            'novelty_type_code' => 'IGE',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['start_date', 'end_date']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeAffiliate(string $statusCode): Affiliate
    {
        $statusId = AffiliateStatus::query()->where('code', $statusCode)->value('id');
        $this->assertNotNull($statusId, "Estado '{$statusCode}' no encontrado en la BD");

        $person = Person::query()->create([
            'document_type' => 'CC',
            'document_number' => (string) random_int(100_000_000, 999_999_999),
            'first_name' => 'Test',
            'first_surname' => 'Novelty',
        ]);

        return Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
            'status_id' => $statusId,
            'mora_status' => 'AL_DIA',
        ]);
    }

    /** Crea N entidades SS del tipo dado y devuelve el array. */
    private function makeEntities(string $type, int $count): array
    {
        $entities = [];
        for ($i = 0; $i < $count; $i++) {
            $entities[] = SSEntity::query()->create([
                'pila_code' => strtoupper($type).random_int(1, 9999),
                'name' => "{$type} Demo {$i}",
                'type' => $type,
                'status' => 'ACTIVE',
            ]);
        }

        return $entities;
    }

    private function seedProfile(
        Affiliate $affiliate,
        ?int $eps_entity_id = null,
        ?int $afp_entity_id = null,
        int $arl_risk_class = 1,
    ): void {
        SocialSecurityProfile::query()->create([
            'affiliate_id' => $affiliate->id,
            'eps_entity_id' => $eps_entity_id,
            'afp_entity_id' => $afp_entity_id,
            'arl_entity_id' => null,
            'eps_tarifa' => 0.125,
            'afp_tarifa' => 0.16,
            'arl_tarifa' => 0.00522,
            'arl_risk_class' => $arl_risk_class,
            'ibc' => 1_423_500,
            'valid_from' => '2026-01-01',
            'valid_until' => null,
        ]);
    }
}
