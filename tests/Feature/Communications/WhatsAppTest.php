<?php

namespace Tests\Feature\Communications;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Events\MoraBeneficiaryAlertNeeded;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Communications\Models\WhatsappLog;
use App\Modules\Communications\Services\WhatsAppOutboundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_template_logs_row_with_sent_when_no_twilio_config(): void
    {
        $person = Person::query()->create([
            'document_number' => '778899',
            'first_name' => 'Cel',
            'first_surname' => 'User',
            'gender' => 'M',
            'address' => 'X',
            'cellphone' => '3001234567',
        ]);

        $affiliate = Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ]);

        $log = app(WhatsAppOutboundService::class)->sendTemplate($affiliate, 'welcome', [
            'name' => 'Cel',
        ]);

        $this->assertSame('sent', $log->status);
        $this->assertSame('log', $log->provider);
        $this->assertSame(1, WhatsappLog::query()->count());
    }

    public function test_mora_event_listener_creates_log(): void
    {
        $person = Person::query()->create([
            'document_number' => '887766',
            'first_name' => 'Mor',
            'first_surname' => 'A',
            'gender' => 'M',
            'address' => 'Y',
            'cellphone' => '3109876543',
        ]);

        $affiliate = Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ]);

        event(new MoraBeneficiaryAlertNeeded($affiliate->fresh(['person'])));

        $this->assertTrue(
            WhatsappLog::query()->where('template_code', 'mora_beneficiary_alert')->exists()
        );
    }
}
