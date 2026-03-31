<?php

namespace Tests\Unit\Modules\Affiliates;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Services\AffiliateStatusMachine;
use App\Modules\RegulatoryEngine\Models\AffiliateStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @see DOCUMENTO_RECTOR §5.4, RN-05, RF-071..RF-074
 */
class AffiliateStatusMachineTest extends TestCase
{
    use RefreshDatabase;

    private AffiliateStatusMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new AffiliateStatusMachine;
        $this->seedStatuses();
    }

    public function test_rn_05_escalate_moves_up_one_level(): void
    {
        $affiliate = $this->createAffiliateWithStatus('ACTIVO');

        $newStatus = $this->machine->escalate($affiliate);

        $this->assertSame('SUSPENDIDO', $newStatus);
    }

    public function test_rn_05_deescalate_moves_down_one_level(): void
    {
        $affiliate = $this->createAffiliateWithStatus('MORA_30');

        $newStatus = $this->machine->deescalate($affiliate);

        $this->assertSame('SUSPENDIDO', $newStatus);
    }

    public function test_rf_072_escalation_never_skips_levels(): void
    {
        $affiliate = $this->createAffiliateWithStatus('ACTIVO');

        $this->assertSame('SUSPENDIDO', $this->machine->escalate($affiliate));
        $this->assertSame('MORA_30', $this->machine->escalate($affiliate));
        $this->assertSame('MORA_60', $this->machine->escalate($affiliate));
        $this->assertSame('MORA_90', $this->machine->escalate($affiliate));
        $this->assertSame('MORA_120', $this->machine->escalate($affiliate));
        $this->assertSame('MORA_120_PLUS', $this->machine->escalate($affiliate));
    }

    public function test_rf_073_deescalation_never_jumps_to_activo(): void
    {
        $affiliate = $this->createAffiliateWithStatus('MORA_60');

        $this->assertSame('MORA_30', $this->machine->deescalate($affiliate));
        $this->assertSame('SUSPENDIDO', $this->machine->deescalate($affiliate));
        $this->assertSame('ACTIVO', $this->machine->deescalate($affiliate));
    }

    public function test_escalate_caps_at_mora_120_plus(): void
    {
        $affiliate = $this->createAffiliateWithStatus('MORA_120_PLUS');

        $newStatus = $this->machine->escalate($affiliate);

        $this->assertSame('MORA_120_PLUS', $newStatus);
    }

    public function test_deescalate_caps_at_afiliado(): void
    {
        $affiliate = $this->createAffiliateWithStatus('AFILIADO');

        $newStatus = $this->machine->deescalate($affiliate);

        $this->assertSame('AFILIADO', $newStatus);
    }

    public function test_rf_074_beneficiary_alert_required_above_mora_30(): void
    {
        $this->assertFalse($this->machine->requiresBeneficiaryAlert(
            $this->createAffiliateWithStatus('MORA_30')
        ));

        $this->assertTrue($this->machine->requiresBeneficiaryAlert(
            $this->createAffiliateWithStatus('MORA_60')
        ));

        $this->assertTrue($this->machine->requiresBeneficiaryAlert(
            $this->createAffiliateWithStatus('MORA_120_PLUS')
        ));
    }

    public function test_retire_sets_retirado(): void
    {
        $affiliate = $this->createAffiliateWithStatus('ACTIVO');

        $this->machine->retire($affiliate);

        $affiliate->refresh();
        $this->assertSame('RETIRADO', $affiliate->status->code);
    }

    public function test_mora_status_field_updates_correctly(): void
    {
        $affiliate = $this->createAffiliateWithStatus('ACTIVO');

        $this->machine->escalate($affiliate);
        $affiliate->refresh();
        $this->assertSame('SUSPENDIDO', $affiliate->mora_status);

        $this->machine->escalate($affiliate);
        $affiliate->refresh();
        $this->assertSame('EN_MORA', $affiliate->mora_status);
    }

    private function seedStatuses(): void
    {
        $statuses = [
            ['code' => 'AFILIADO', 'name' => 'Afiliado', 'sort_order' => 10],
            ['code' => 'ACTIVO', 'name' => 'Activo', 'sort_order' => 20],
            ['code' => 'PAGO_MES_SUBSIGUIENTE', 'name' => 'Pago mes subsiguiente', 'sort_order' => 15],
            ['code' => 'SUSPENDIDO', 'name' => 'Suspendido', 'sort_order' => 30],
            ['code' => 'MORA_30', 'name' => 'Mora 30 días', 'sort_order' => 40],
            ['code' => 'MORA_60', 'name' => 'Mora 60 días', 'sort_order' => 50],
            ['code' => 'MORA_90', 'name' => 'Mora 90 días', 'sort_order' => 60],
            ['code' => 'MORA_120', 'name' => 'Mora 120 días', 'sort_order' => 65],
            ['code' => 'MORA_120_PLUS', 'name' => 'Mora +120 días', 'sort_order' => 68],
            ['code' => 'RETIRADO', 'name' => 'Retirado', 'sort_order' => 80],
        ];

        foreach ($statuses as $s) {
            AffiliateStatus::query()->firstOrCreate(['code' => $s['code']], $s);
        }
    }

    private function createAffiliateWithStatus(string $statusCode): Affiliate
    {
        $status = AffiliateStatus::query()->where('code', $statusCode)->first();

        $person = \App\Modules\Affiliates\Models\Person::query()->create([
            'document_type' => 'CC',
            'document_number' => (string) random_int(100000000, 999999999),
            'first_name' => 'Test',
            'first_surname' => 'User',
        ]);

        return Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => 'SERVICONLI',
            'status_id' => $status->id,
            'mora_status' => 'AL_DIA',
        ]);
    }
}
