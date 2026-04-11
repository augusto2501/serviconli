<?php

namespace Tests\Feature\Dashboard;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Beneficiary;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Billing\Models\BillInvoice;
use App\Modules\Billing\Models\PaymentReceived;
use App\Modules\RegulatoryEngine\Models\AffiliateStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_all_sections(): void
    {
        $response = $this->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'affiliates' => ['total', 'active', 'mora', 'inactive'],
                'revenue' => ['current_month_pesos', 'previous_month_pesos', 'variation_percent'],
                'pila' => ['liquidations_total', 'liquidations_confirmed', 'files_generated'],
                'enrollments' => ['current_month', 'previous_month'],
                'distribution' => ['by_client_type', 'by_pila_operator'],
                'alerts' => ['mora_over_90_days', 'beneficiaries_turning_18', 'student_certs_expiring'],
                'generated_at',
            ]);
    }

    public function test_dashboard_counts_affiliates_by_status(): void
    {
        $activoId = AffiliateStatus::query()->where('code', 'ACTIVO')->value('id');
        $mora60Id = AffiliateStatus::query()->where('code', 'MORA_60')->value('id');
        $retiradoId = AffiliateStatus::query()->where('code', 'RETIRADO')->value('id');

        $this->createAffiliate(['status_id' => $activoId]);
        $this->createAffiliate(['status_id' => $activoId]);
        $this->createAffiliate(['status_id' => $mora60Id, 'mora_status' => 'EN_MORA']);
        $this->createAffiliate(['status_id' => $retiradoId]);

        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $affiliates = $response->json('affiliates');
        $this->assertSame(4, $affiliates['total']);
        $this->assertSame(2, $affiliates['active']);
        $this->assertSame(1, $affiliates['mora']);
        $this->assertSame(1, $affiliates['inactive']);
    }

    public function test_dashboard_revenue_current_vs_previous(): void
    {
        $now = Carbon::create(2026, 4, 15);
        Carbon::setTestNow($now);

        $invoice = BillInvoice::query()->create([
            'tipo' => 'APORTE',
            'total_pesos' => 500000,
            'status' => 'PAGADO',
        ]);

        PaymentReceived::query()->create([
            'invoice_id' => $invoice->id,
            'amount_pesos' => 500000,
            'payment_method' => 'EFECTIVO',
            'payment_date' => '2026-04-10',
            'status' => 'APLICADO',
        ]);

        PaymentReceived::query()->create([
            'invoice_id' => $invoice->id,
            'amount_pesos' => 300000,
            'payment_method' => 'EFECTIVO',
            'payment_date' => '2026-03-15',
            'status' => 'APLICADO',
        ]);

        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $revenue = $response->json('revenue');
        $this->assertSame(500000, $revenue['current_month_pesos']);
        $this->assertSame(300000, $revenue['previous_month_pesos']);
        $this->assertSame(66.7, $revenue['variation_percent']);
    }

    public function test_dashboard_alerts_beneficiaries_turning_18(): void
    {
        $now = Carbon::create(2026, 4, 10);
        Carbon::setTestNow($now);

        $affiliate = $this->createAffiliate();

        // Cumple 18 en 15 días → dentro de ventana
        Beneficiary::query()->create([
            'affiliate_id' => $affiliate->id,
            'document_type' => 'TI',
            'document_number' => '1001001',
            'first_name' => 'Junior',
            'surnames' => 'Test',
            'birth_date' => $now->copy()->subYears(18)->addDays(15)->toDateString(),
            'gender' => 'M',
            'parentesco' => 'HIJO',
            'status' => 'ACTIVO',
        ]);

        // Cumple 18 en 60 días → fuera de ventana
        Beneficiary::query()->create([
            'affiliate_id' => $affiliate->id,
            'document_type' => 'TI',
            'document_number' => '1001002',
            'first_name' => 'Junior2',
            'surnames' => 'Test',
            'birth_date' => $now->copy()->subYears(18)->addDays(60)->toDateString(),
            'gender' => 'M',
            'parentesco' => 'HIJO',
            'status' => 'ACTIVO',
        ]);

        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $this->assertSame(1, $response->json('alerts.beneficiaries_turning_18'));
    }

    public function test_dashboard_alerts_student_cert_expiring(): void
    {
        $now = Carbon::create(2026, 4, 10);
        Carbon::setTestNow($now);

        $affiliate = $this->createAffiliate();

        Beneficiary::query()->create([
            'affiliate_id' => $affiliate->id,
            'document_type' => 'TI',
            'document_number' => '2002001',
            'first_name' => 'Student',
            'surnames' => 'Test',
            'birth_date' => '2010-01-01',
            'gender' => 'F',
            'parentesco' => 'HIJA',
            'student_cert_expires' => '2026-04-20',
            'status' => 'ACTIVO',
        ]);

        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $this->assertSame(1, $response->json('alerts.student_certs_expiring'));
    }

    public function test_reports_daily_contributions(): void
    {
        $invoice = BillInvoice::query()->create([
            'tipo' => 'APORTE',
            'total_pesos' => 200000,
            'status' => 'PAGADO',
        ]);

        PaymentReceived::query()->create([
            'invoice_id' => $invoice->id,
            'amount_pesos' => 200000,
            'payment_method' => 'EFECTIVO',
            'payment_date' => '2026-04-10',
            'status' => 'APLICADO',
        ]);

        $response = $this->getJson('/api/reports/daily-contributions?date=2026-04-10');

        $response->assertOk()
            ->assertJsonFragment(['contributions_count' => 1])
            ->assertJsonFragment(['total_pesos' => 200000]);
    }

    public function test_reports_mora(): void
    {
        $mora30Id = AffiliateStatus::query()->where('code', 'MORA_30')->value('id');
        $mora90Id = AffiliateStatus::query()->where('code', 'MORA_90')->value('id');

        $this->createAffiliate(['status_id' => $mora30Id, 'mora_status' => 'EN_MORA']);
        $this->createAffiliate(['status_id' => $mora90Id, 'mora_status' => 'EN_MORA']);

        $response = $this->getJson('/api/reports/mora');

        $response->assertOk();
        $this->assertSame(2, $response->json('total_in_mora'));
        $this->assertSame(1, $response->json('by_level.MORA_30'));
        $this->assertSame(1, $response->json('by_level.MORA_90'));
    }

    public function test_reports_affiliates_by_advisor(): void
    {
        $response = $this->getJson('/api/reports/affiliates-by-advisor');

        $response->assertOk();
        $this->assertIsArray($response->json());
    }

    public function test_reports_affiliates_by_employer(): void
    {
        $response = $this->getJson('/api/reports/affiliates-by-employer');

        $response->assertOk();
        $this->assertIsArray($response->json());
    }

    public function test_reports_cash_reconciliation_empty(): void
    {
        $response = $this->getJson('/api/reports/cash-reconciliation?date=2026-04-10');

        $response->assertOk()
            ->assertJsonFragment(['status' => 'NO_ABIERTO']);
    }

    public function test_reports_end_of_day(): void
    {
        $response = $this->getJson('/api/reports/end-of-day?date=2026-04-10');

        $response->assertOk()
            ->assertJsonStructure([
                'date',
                'payments',
                'cash_reconciliation',
                'liquidations_confirmed_today',
            ]);
    }

    private function createAffiliate(array $extra = []): Affiliate
    {
        $person = Person::query()->create([
            'document_number' => 'D'.random_int(100000, 999999),
            'first_name' => 'Test',
            'first_surname' => 'User',
            'gender' => 'M',
            'address' => 'Calle Test',
        ]);

        return Affiliate::query()->create(array_merge([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ], $extra));
    }
}
