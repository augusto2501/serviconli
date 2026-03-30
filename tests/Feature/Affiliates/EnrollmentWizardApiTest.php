<?php

namespace Tests\Feature\Affiliates;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\EnrollmentProcess;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Affiliations\Models\SocialSecurityProfile;
use App\Modules\RegulatoryEngine\Models\SSEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentWizardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_happy_path_1_to_6_persists_affiliate_and_related_data(): void
    {
        $eps = SSEntity::query()->create([
            'pila_code' => 'EPS01',
            'name' => 'EPS Demo',
            'type' => 'EPS',
            'status' => 'ACTIVE',
        ]);

        $step1 = $this->postJson('/api/enrollment/step-1', [
            'client_type' => 'SERVICONLI',
            'contributor_type_code' => '01',
            'subtipo' => 11,
            'is_type_51' => false,
        ]);
        $step1->assertCreated()->assertJsonPath('currentStep', 1);
        $processId = (int) $step1->json('processId');

        $this->postJson('/api/enrollment/step-2', [
            'process_id' => $processId,
            'document_type' => 'CC',
            'document_number' => '99887766',
            'first_name' => 'Laura',
            'first_surname' => 'Diaz',
            'gender' => 'F',
            'address' => 'Calle 1',
            'cellphone' => '3001112222',
        ])->assertOk()->assertJsonPath('currentStep', 2);

        $this->postJson('/api/enrollment/step-3', [
            'process_id' => $processId,
            'beneficiaries' => [
                [
                    'document_type' => 'TI',
                    'document_number' => 'B-1',
                    'first_name' => 'Hijo',
                    'surnames' => 'Diaz',
                    'parentesco' => 'HIJO',
                ],
            ],
        ])->assertOk()->assertJsonPath('currentStep', 3);

        $this->postJson('/api/enrollment/step-4', [
            'process_id' => $processId,
            'eps_entity_id' => $eps->id,
            'valid_from' => '2026-01-01',
        ])->assertOk()->assertJsonPath('currentStep', 4);

        $this->postJson('/api/enrollment/step-5', [
            'process_id' => $processId,
            'payment_method' => 'EFECTIVO',
            'billing_mode' => 'INDIVIDUAL',
        ])->assertOk()->assertJsonPath('currentStep', 5);

        $confirm = $this->postJson('/api/enrollment/step-6/confirm', [
            'process_id' => $processId,
        ]);
        $confirm->assertOk()->assertJsonPath('status', 'COMPLETED');

        $this->assertSame(1, Person::query()->count());
        $this->assertSame(1, Affiliate::query()->count());
        $this->assertSame(1, SocialSecurityProfile::query()->count());
        $this->assertDatabaseCount('afl_beneficiaries', 1);

        $process = EnrollmentProcess::query()->find($processId);
        $this->assertNotNull($process);
        $this->assertSame('COMPLETED', $process->status);
        $this->assertNotNull($process->affiliate_id);
    }

    public function test_rejects_skipping_previous_step(): void
    {
        $step1 = $this->postJson('/api/enrollment/step-1', [
            'client_type' => 'SERVICONLI',
            'contributor_type_code' => '01',
        ]);
        $processId = (int) $step1->json('processId');

        $response = $this->postJson('/api/enrollment/step-3', [
            'process_id' => $processId,
            'beneficiaries' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_step2_validates_rf_005_rf_006_required_fields(): void
    {
        $step1 = $this->postJson('/api/enrollment/step-1', [
            'client_type' => 'SERVICONLI',
            'contributor_type_code' => '01',
        ]);
        $processId = (int) $step1->json('processId');

        $response = $this->postJson('/api/enrollment/step-2', [
            'process_id' => $processId,
            'document_type' => 'CC',
            'document_number' => '123',
            'first_name' => 'Ana',
            // first_surname missing
            'gender' => 'F',
            // address missing
            // no phones
        ]);

        $response->assertStatus(422);
    }
}
