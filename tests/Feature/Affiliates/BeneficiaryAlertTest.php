<?php

namespace Tests\Feature\Affiliates;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Beneficiary;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Communications\Models\CommNotification;
use App\Modules\Communications\Models\WhatsappLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeneficiaryAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_beneficiary_turning_18_within_30_days(): void
    {
        $now = Carbon::create(2026, 4, 10);
        Carbon::setTestNow($now);

        $affiliate = $this->createAffiliate();

        Beneficiary::query()->create([
            'affiliate_id' => $affiliate->id,
            'document_type' => 'TI',
            'document_number' => '100100',
            'first_name' => 'Junior',
            'surnames' => 'Pérez',
            'birth_date' => '2008-04-25',
            'gender' => 'M',
            'parentesco' => 'HIJO',
            'status' => 'ACTIVO',
        ]);

        $this->artisan('beneficiaries:alert')
            ->assertExitCode(0);

        $this->assertSame(1, CommNotification::query()->count());
        $notif = CommNotification::query()->first();
        $this->assertSame('ALERTA_BENEFICIARIO', $notif->type);
        $this->assertStringContainsString('Junior Pérez', $notif->body);
        $this->assertStringContainsString('18 años', $notif->title);
    }

    public function test_detects_student_cert_expiring(): void
    {
        $now = Carbon::create(2026, 4, 10);
        Carbon::setTestNow($now);

        $affiliate = $this->createAffiliate();

        Beneficiary::query()->create([
            'affiliate_id' => $affiliate->id,
            'document_type' => 'TI',
            'document_number' => '200200',
            'first_name' => 'Estudiante',
            'surnames' => 'López',
            'birth_date' => '2010-03-01',
            'gender' => 'F',
            'parentesco' => 'HIJA',
            'student_cert_expires' => '2026-04-30',
            'status' => 'ACTIVO',
        ]);

        $this->artisan('beneficiaries:alert')
            ->assertExitCode(0);

        $this->assertSame(1, CommNotification::query()->count());
        $notif = CommNotification::query()->first();
        $this->assertStringContainsString('certificado de estudiante', mb_strtolower($notif->title));
    }

    public function test_detects_protection_end_date(): void
    {
        $now = Carbon::create(2026, 4, 10);
        Carbon::setTestNow($now);

        $affiliate = $this->createAffiliate();

        Beneficiary::query()->create([
            'affiliate_id' => $affiliate->id,
            'document_type' => 'TI',
            'document_number' => '300300',
            'first_name' => 'Protegido',
            'surnames' => 'García',
            'birth_date' => '2012-06-01',
            'gender' => 'M',
            'parentesco' => 'HIJO',
            'protection_end_date' => '2026-05-01',
            'status' => 'ACTIVO',
        ]);

        $this->artisan('beneficiaries:alert')
            ->assertExitCode(0);

        $this->assertSame(1, CommNotification::query()->count());
        $this->assertStringContainsString('protección', mb_strtolower(CommNotification::query()->first()->title));
    }

    public function test_no_alerts_outside_window(): void
    {
        $now = Carbon::create(2026, 4, 10);
        Carbon::setTestNow($now);

        $affiliate = $this->createAffiliate();

        // Cumple 18 en 60 días — fuera de ventana de 30
        Beneficiary::query()->create([
            'affiliate_id' => $affiliate->id,
            'document_type' => 'TI',
            'document_number' => '400400',
            'first_name' => 'Lejano',
            'surnames' => 'Test',
            'birth_date' => '2008-06-10',
            'gender' => 'M',
            'parentesco' => 'HIJO',
            'status' => 'ACTIVO',
        ]);

        $this->artisan('beneficiaries:alert')
            ->assertExitCode(0);

        $this->assertSame(0, CommNotification::query()->count());
    }

    public function test_dry_run_creates_no_notifications(): void
    {
        $now = Carbon::create(2026, 4, 10);
        Carbon::setTestNow($now);

        $affiliate = $this->createAffiliate();

        Beneficiary::query()->create([
            'affiliate_id' => $affiliate->id,
            'document_type' => 'TI',
            'document_number' => '500500',
            'first_name' => 'DryRun',
            'surnames' => 'Kid',
            'birth_date' => '2008-04-25',
            'gender' => 'M',
            'parentesco' => 'HIJO',
            'status' => 'ACTIVO',
        ]);

        $this->artisan('beneficiaries:alert --dry-run')
            ->assertExitCode(0);

        $this->assertSame(0, CommNotification::query()->count());
    }

    public function test_sends_whatsapp_when_alert_created(): void
    {
        $now = Carbon::create(2026, 4, 10);
        Carbon::setTestNow($now);

        $affiliate = $this->createAffiliate();

        Beneficiary::query()->create([
            'affiliate_id' => $affiliate->id,
            'document_type' => 'TI',
            'document_number' => '600600',
            'first_name' => 'WA',
            'surnames' => 'Test',
            'birth_date' => '2008-04-20',
            'gender' => 'M',
            'parentesco' => 'HIJO',
            'status' => 'ACTIVO',
        ]);

        $this->artisan('beneficiaries:alert')
            ->assertExitCode(0);

        $this->assertTrue(WhatsappLog::query()->where('affiliate_id', $affiliate->id)->exists());
    }

    private function createAffiliate(): Affiliate
    {
        $person = Person::query()->create([
            'document_number' => 'P'.random_int(100000, 999999),
            'first_name' => 'Padre',
            'first_surname' => 'Test',
            'gender' => 'M',
            'address' => 'Calle Test 123',
            'cellphone' => '3001234567',
        ]);

        return Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ]);
    }
}
