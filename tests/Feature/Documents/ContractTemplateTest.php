<?php

namespace Tests\Feature\Documents;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Documents\Services\ContractTemplateRegistry;
use App\Modules\PILALiquidation\Enums\PilaLiquidationStatus;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\PILALiquidation\Models\PilaLiquidationLine;
use App\Modules\RegulatoryEngine\Models\AffiliateStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContractTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_static_contract_templates_return_pdf(): void
    {
        $affiliate = $this->makeAffiliate();

        $codes = array_values(array_filter(
            ContractTemplateRegistry::codes(),
            static fn (string $c): bool => $c !== 'payment_certificate',
        ));

        foreach ($codes as $code) {
            $r = $this->get('/api/affiliates/'.$affiliate->id.'/contract-documents/'.$code);
            $r->assertOk();
            $this->assertStringContainsString('application/pdf', (string) $r->headers->get('Content-Type'));
            $this->assertSame((string) ContractTemplateRegistry::versionFor($code), $r->headers->get('X-Contract-Template-Version'));
            $this->assertGreaterThan(400, strlen($r->streamedContent()));
        }
    }

    public function test_payment_certificate_full_and_summary_when_paid(): void
    {
        $affiliate = $this->makeAffiliate();
        $this->seedPilaLine($affiliate, 2026, 2);

        $full = $this->get('/api/affiliates/'.$affiliate->id.'/contract-documents/payment_certificate?year=2026&month=2&format=full');
        $full->assertOk();
        $this->assertGreaterThan(400, strlen($full->streamedContent()));

        $sum = $this->get('/api/affiliates/'.$affiliate->id.'/contract-documents/payment_certificate?year=2026&month=2&format=summary');
        $sum->assertOk();
        $this->assertGreaterThan(200, strlen($sum->streamedContent()));
        $this->assertNotSame($full->streamedContent(), $sum->streamedContent());
    }

    public function test_payment_certificate_422_when_unpaid(): void
    {
        $affiliate = $this->makeAffiliate();

        $this->get('/api/affiliates/'.$affiliate->id.'/contract-documents/payment_certificate?year=2026&month=3')
            ->assertStatus(422);
    }

    private function makeAffiliate(): Affiliate
    {
        $statusId = AffiliateStatus::query()->where('code', 'ACTIVO')->value('id');
        $this->assertNotNull($statusId);

        $person = Person::query()->create([
            'document_type' => 'CC',
            'document_number' => (string) random_int(100_000_000, 999_999_999),
            'first_name' => 'Doc',
            'first_surname' => 'Test',
        ]);

        return Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
            'status_id' => $statusId,
            'mora_status' => 'AL_DIA',
        ]);
    }

    private function seedPilaLine(Affiliate $affiliate, int $year, int $month): void
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
