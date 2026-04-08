<?php

namespace Tests\Feature\Advisors;

use App\Modules\Advisors\Models\Advisor;
use App\Modules\Advisors\Models\AdvisorCommission;
use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\EnrollmentProcess;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Affiliates\Services\PostEnrollmentCompletionService;
use App\Modules\Communications\Models\WhatsappLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_enrollment_completion_creates_commission_with_ce_number(): void
    {
        $advisor = Advisor::query()->create([
            'code' => 'AS-C1',
            'first_name' => 'Com',
            'commission_new' => 25_000,
            'commission_recurring' => 5_000,
            'authorizes_credits' => false,
        ]);

        $person = Person::query()->create([
            'document_number' => '990011',
            'first_name' => 'X',
            'first_surname' => 'Y',
            'gender' => 'M',
            'address' => 'Calle',
        ]);

        $affiliate = Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ]);

        $process = EnrollmentProcess::query()->create([
            'status' => 'COMPLETED',
            'current_step' => 6,
            'affiliate_id' => $affiliate->id,
            'step5_payload' => [
                'payment_method' => 'EFECTIVO',
                'raw_ibc_pesos' => 1_000_000,
                'advisor_id' => $advisor->id,
            ],
        ]);

        app(PostEnrollmentCompletionService::class)->handle($process, $affiliate);

        $this->assertTrue(
            WhatsappLog::query()
                ->where('affiliate_id', $affiliate->id)
                ->where('template_code', 'welcome')
                ->exists()
        );

        $this->assertSame(1, AdvisorCommission::query()->count());
        $row = AdvisorCommission::query()->first();
        $this->assertSame('NEW', $row->commission_type);
        $this->assertSame(25_000, (int) $row->amount_pesos);
        $this->assertSame('CALCULADA', $row->status);
        $this->assertMatchesRegularExpression('/^CE-\d{4}-\d{4}$/', $row->public_number);

        $this->patchJson('/api/advisor-commissions/'.$row->id, [
            'status' => 'PAGADA',
        ])->assertOk()->assertJsonPath('status', 'PAGADA');
    }
}
