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

class PaymentCertificatePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_returns_422_when_unpaid(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');

        $r = $this->get('/api/affiliates/'.$affiliate->id.'/payment-certificate/pdf?year=2026&month=1');

        $r->assertStatus(422)
            ->assertJsonPath('message', 'No hay liquidación PILA confirmada para este período.');
    }

    public function test_pdf_download_when_paid(): void
    {
        $affiliate = $this->makeAffiliate('ACTIVO');
        $this->seedLine($affiliate, 2026, 1);

        $r = $this->get('/api/affiliates/'.$affiliate->id.'/payment-certificate/pdf?year=2026&month=1');

        $r->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $r->headers->get('Content-Type'));
        $this->assertGreaterThan(500, strlen($r->streamedContent()));
    }

    private function makeAffiliate(string $statusCode): Affiliate
    {
        $statusId = AffiliateStatus::query()->where('code', $statusCode)->value('id');
        $this->assertNotNull($statusId);

        $person = Person::query()->create([
            'document_type' => 'CC',
            'document_number' => (string) random_int(100_000_000, 999_999_999),
            'first_name' => 'PDF',
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
