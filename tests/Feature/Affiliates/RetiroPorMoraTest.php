<?php

namespace Tests\Feature\Affiliates;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Affiliates\Services\NoveltyService;
use App\Modules\RegulatoryEngine\Models\AffiliateStatus;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * RF-063 — Retiro por mora.
 * Causal MORA_EN_APORTE: días=1, admin_fee=$0, retiro TOTAL forzado.
 *
 * @see DOCUMENTO_RECTOR §5.2, SKILL.md CASO 12
 */
class RetiroPorMoraTest extends TestCase
{
    use RefreshDatabase;

    private NoveltyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NoveltyService::class);
    }

    public function test_mora_retirement_retires_affiliate_to_retirado(): void
    {
        // RF-063: retiro por mora siempre es TOTAL → RETIRADO
        $affiliate = $this->makeAffiliate('MORA_60');
        $period = new Periodo(2026, 4);

        $novelty = $this->service->register($affiliate, $period, 'RET', [
            'retirement_cause' => 'MORA_EN_APORTE',
            'retirement_scope' => 'TOTAL',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-01', // días = 1
        ]);

        $affiliate->refresh();
        $this->assertSame('RETIRADO', $affiliate->status->code);
        $this->assertSame('MORA_EN_APORTE', $novelty->retirement_cause);
    }

    public function test_mora_retirement_forces_total_scope_even_if_not_declared(): void
    {
        // RF-063: aunque no se declare retirement_scope, MORA_EN_APORTE fuerza TOTAL
        $affiliate = $this->makeAffiliate('MORA_30');
        $period = new Periodo(2026, 4);

        $this->service->register($affiliate, $period, 'RET', [
            'retirement_cause' => 'MORA_EN_APORTE',
            'retirement_scope' => null,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-01',
        ]);

        $affiliate->refresh();
        $this->assertSame('RETIRADO', $affiliate->status->code);
    }

    public function test_is_mora_retirement_detects_correct_cause(): void
    {
        // RF-063: helper isMoraRetirement() distingue causa correctamente
        $affiliate = $this->makeAffiliate('ACTIVO');
        $period = new Periodo(2026, 4);

        $mora = $this->service->register($affiliate, $period, 'RET', [
            'retirement_cause' => 'MORA_EN_APORTE',
            'retirement_scope' => 'TOTAL',
        ]);

        $normal = $this->service->register(
            $this->makeAffiliate('ACTIVO'),
            $period,
            'RET',
            ['retirement_cause' => 'VOLUNTARIO', 'retirement_scope' => 'TOTAL'],
        );

        $this->assertTrue($this->service->isMoraRetirement($mora));
        $this->assertFalse($this->service->isMoraRetirement($normal));
    }

    public function test_mora_retirement_requires_arl_alert_rn28(): void
    {
        // RF-063 + RN-28: retiro por mora es TOTAL efectivo → alerta ARL (plataforma)
        Event::fake();
        $affiliate = $this->makeAffiliate('MORA_60');
        $period = new Periodo(2026, 4);

        $novelty = $this->service->register($affiliate, $period, 'RET', [
            'retirement_cause' => 'MORA_EN_APORTE',
            'retirement_scope' => 'TOTAL',
        ]);

        $this->assertTrue($this->service->requiresARLRetirementAlert($novelty));
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function makeAffiliate(string $statusCode): Affiliate
    {
        $statusId = AffiliateStatus::query()->where('code', $statusCode)->value('id');
        $this->assertNotNull($statusId, "Estado '{$statusCode}' no encontrado");

        $person = Person::query()->create([
            'document_type' => 'CC',
            'document_number' => (string) random_int(100_000_000, 999_999_999),
            'first_name' => 'Mora',
            'first_surname' => 'Test',
        ]);

        return Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
            'status_id' => $statusId,
            'mora_status' => str_starts_with($statusCode, 'MORA_') ? 'EN_MORA' : 'AL_DIA',
        ]);
    }
}
