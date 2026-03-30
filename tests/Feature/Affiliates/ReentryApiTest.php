<?php

namespace Tests\Feature\Affiliates;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Affiliations\Models\AffiliatePayer;
use App\Modules\Affiliations\Models\Payer;
use App\Modules\Affiliations\Models\SocialSecurityProfile;
use App\Modules\Billing\Models\BillInvoice;
use App\Modules\RegulatoryEngine\Models\AffiliateStatus;
use App\Modules\RegulatoryEngine\Models\SSEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReentryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reentry_happy_path_rf_012_to_rf_014(): void
    {
        $retiradoId = AffiliateStatus::query()->where('code', 'RETIRADO')->value('id');
        $this->assertNotNull($retiradoId);

        $eps = SSEntity::query()->create([
            'pila_code' => 'EPS-R',
            'name' => 'EPS Reentry',
            'type' => 'EPS',
            'status' => 'ACTIVE',
        ]);

        $person = Person::query()->create([
            'document_type' => 'CC',
            'document_number' => 'RE-001',
            'first_name' => 'Rita',
            'first_surname' => 'Ree',
            'gender' => 'F',
            'address' => 'Calle Re',
            'cellphone' => '3001234567',
        ]);

        $affiliate = Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
            'status_id' => $retiradoId,
        ]);

        SocialSecurityProfile::query()->create([
            'affiliate_id' => $affiliate->id,
            'eps_entity_id' => $eps->id,
            'valid_from' => '2020-01-01',
            'valid_until' => null,
        ]);

        $payer = Payer::query()->create([
            'nit' => '900999888',
            'digito_verificacion' => 1,
            'razon_social' => 'Empresa Test',
            'status' => 'ACTIVE',
        ]);

        AffiliatePayer::query()->create([
            'affiliate_id' => $affiliate->id,
            'payer_id' => $payer->id,
            'start_date' => '2020-01-01',
            'end_date' => null,
            'contributor_type_code' => '01',
        ]);

        $this->getJson('/api/reentry/eligible?document_number=RE-001')
            ->assertOk()
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('statusCode', 'RETIRADO');

        $start = $this->postJson('/api/reentry/start', ['affiliate_id' => $affiliate->id]);
        $start->assertCreated();
        $processId = (int) $start->json('processId');

        $this->postJson('/api/reentry/step-1', [
            'process_id' => $processId,
            'document_type' => 'CC',
            'document_number' => 'RE-001',
            'first_name' => 'Rita',
            'first_surname' => 'Ree',
            'gender' => 'F',
            'address' => 'Nueva dirección',
            'cellphone' => '3001234567',
        ])->assertOk()->assertJsonPath('currentStep', 2);

        $this->postJson('/api/reentry/step-2', [
            'process_id' => $processId,
            'eps_entity_id' => $eps->id,
            'valid_from' => '2026-04-01',
        ])->assertOk()->assertJsonPath('currentStep', 3);

        $this->postJson('/api/reentry/step-3', [
            'process_id' => $processId,
            'payer_id' => $payer->id,
            'contributor_type_code' => '01',
            'start_date' => '2026-04-01',
        ])->assertOk()->assertJsonPath('currentStep', 4);

        $confirm = $this->postJson('/api/reentry/confirm', [
            'process_id' => $processId,
            'payment_method' => 'EFECTIVO',
            'invoice_total_pesos' => 150_000,
        ]);
        $confirm->assertOk()->assertJsonPath('status', 'COMPLETED');

        $afiliadoId = AffiliateStatus::query()->where('code', 'AFILIADO')->value('id');
        $affiliate->refresh();
        $this->assertSame($afiliadoId, $affiliate->status_id);

        $invoice = BillInvoice::query()->find($confirm->json('billInvoiceId'));
        $this->assertNotNull($invoice);
        $this->assertSame('03', $invoice->tipo);
        $this->assertSame(150_000, $invoice->total_pesos);
        $this->assertSame('EFECTIVO', $invoice->payment_method);

        $profiles = SocialSecurityProfile::query()->where('affiliate_id', $affiliate->id)->orderBy('id')->get();
        $this->assertCount(2, $profiles);
        $this->assertNotNull($profiles[0]->valid_until);
        $this->assertNull($profiles[1]->valid_until);
        $this->assertSame('2026-04-01', $profiles[1]->valid_from->toDateString());

        $links = AffiliatePayer::query()->where('affiliate_id', $affiliate->id)->orderBy('id')->get();
        $this->assertCount(2, $links);
        $this->assertNotNull($links[0]->end_date);
        $this->assertNull($links[1]->end_date);
    }

    public function test_eligible_returns_404_when_not_retirado_or_inactivo(): void
    {
        $afiliadoId = AffiliateStatus::query()->where('code', 'AFILIADO')->value('id');
        $person = Person::query()->create([
            'document_number' => 'X-99',
            'first_name' => 'A',
            'first_surname' => 'B',
        ]);
        Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
            'status_id' => $afiliadoId,
        ]);

        $this->getJson('/api/reentry/eligible?document_number=X-99')->assertStatus(404);
    }
}
