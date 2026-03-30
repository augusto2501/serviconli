<?php

namespace Tests\Feature\Affiliates;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\EnrollmentProcess;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Affiliations\Models\SocialSecurityProfile;
use App\Modules\RegulatoryEngine\Models\RegulatoryParameter;
use App\Modules\RegulatoryEngine\Models\SSEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentWizardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_happy_path_1_to_6_persists_affiliate_and_related_data(): void
    {
        $this->seedRegulatoryRatesForPila();

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

        $step5 = $this->postJson('/api/enrollment/step-5', [
            'process_id' => $processId,
            'payment_method' => 'EFECTIVO',
            'billing_mode' => 'INDIVIDUAL',
            'raw_ibc_pesos' => 1_750_905,
        ]);
        $step5->assertOk()->assertJsonPath('currentStep', 5);
        $step5->assertJsonStructure([
            'billingPreview' => [
                'entryDate',
                'cotizationYear',
                'cotizationMonth',
                'calendarDaysFromEntryToMonthEnd',
                'billableDaysFirstMonth',
                'monthlyFullSocialSecurityPesos',
                'firstMonthProportionalSocialSecurityPesos',
                'ibcRoundedPesos',
            ],
        ]);

        $confirm = $this->postJson('/api/enrollment/step-6/confirm', [
            'process_id' => $processId,
            'habeas_data_accepted' => true,
        ]);
        $confirm->assertOk()->assertJsonPath('status', 'COMPLETED');
        $radicado = $confirm->json('radicadoNumber');
        $this->assertIsString($radicado);
        $this->assertMatchesRegularExpression('/^RAD-\d{4}-\d{6}$/', $radicado);

        $this->assertSame(1, Person::query()->count());
        $this->assertSame(1, Affiliate::query()->count());
        $this->assertSame(1, SocialSecurityProfile::query()->count());
        $this->assertDatabaseCount('afl_beneficiaries', 1);

        $process = EnrollmentProcess::query()->find($processId);
        $this->assertNotNull($process);
        $this->assertSame('COMPLETED', $process->status);
        $this->assertNotNull($process->affiliate_id);
        $this->assertNotNull($process->radicado_number);
        $this->assertMatchesRegularExpression('/^RAD-\d{4}-\d{6}$/', $process->radicado_number ?? '');
        $this->assertDatabaseCount('gdpr_consent_records', 1);
    }

    public function test_step5_rf_011_proportional_first_month_vs_full_30_days(): void
    {
        $this->seedRegulatoryRatesForPila();

        $eps = SSEntity::query()->create([
            'pila_code' => 'EPS03',
            'name' => 'EPS Three',
            'type' => 'EPS',
            'status' => 'ACTIVE',
        ]);

        $step1 = $this->postJson('/api/enrollment/step-1', [
            'client_type' => 'SERVICONLI',
            'contributor_type_code' => '01',
        ]);
        $processId = (int) $step1->json('processId');

        $this->postJson('/api/enrollment/step-2', [
            'process_id' => $processId,
            'document_type' => 'CC',
            'document_number' => '55667788',
            'first_name' => 'P',
            'first_surname' => 'Q',
            'gender' => 'M',
            'address' => 'Addr',
            'cellphone' => '3000000001',
        ])->assertOk();

        $this->postJson('/api/enrollment/step-3', [
            'process_id' => $processId,
            'beneficiaries' => [
                [
                    'document_number' => 'B-88',
                    'document_type' => 'TI',
                    'first_name' => 'H',
                    'surnames' => 'Q',
                ],
            ],
        ])->assertOk();

        $this->postJson('/api/enrollment/step-4', [
            'process_id' => $processId,
            'eps_entity_id' => $eps->id,
            'valid_from' => '2026-03-15',
        ])->assertOk();

        $r = $this->postJson('/api/enrollment/step-5', [
            'process_id' => $processId,
            'payment_method' => 'EFECTIVO',
            'raw_ibc_pesos' => 1_750_905,
        ]);
        $r->assertOk();
        $preview = $r->json('billingPreview');
        $this->assertSame('2026-03-15', $preview['entryDate']);
        $this->assertSame(17, $preview['calendarDaysFromEntryToMonthEnd']);
        $this->assertSame(17, $preview['billableDaysFirstMonth']);
        $full = (int) $preview['monthlyFullSocialSecurityPesos'];
        $first = (int) $preview['firstMonthProportionalSocialSecurityPesos'];
        $this->assertSame((int) round($full * 17 / 30), $first);
    }

    public function test_confirm_requires_habeas_acceptance(): void
    {
        $this->seedRegulatoryRatesForPila();

        $eps = SSEntity::query()->create([
            'pila_code' => 'EPS02',
            'name' => 'EPS Two',
            'type' => 'EPS',
            'status' => 'ACTIVE',
        ]);

        $step1 = $this->postJson('/api/enrollment/step-1', [
            'client_type' => 'SERVICONLI',
            'contributor_type_code' => '01',
        ]);
        $processId = (int) $step1->json('processId');

        $this->postJson('/api/enrollment/step-2', [
            'process_id' => $processId,
            'document_type' => 'CC',
            'document_number' => '11112222',
            'first_name' => 'X',
            'first_surname' => 'Y',
            'gender' => 'M',
            'address' => 'Addr',
            'cellphone' => '3000000000',
        ])->assertOk();

        $this->postJson('/api/enrollment/step-3', [
            'process_id' => $processId,
            'beneficiaries' => [
                [
                    'document_number' => 'B-99',
                    'document_type' => 'TI',
                    'first_name' => 'H',
                    'surnames' => 'Y',
                ],
            ],
        ])->assertOk();

        $this->postJson('/api/enrollment/step-4', [
            'process_id' => $processId,
            'eps_entity_id' => $eps->id,
        ])->assertOk();

        $this->postJson('/api/enrollment/step-5', [
            'process_id' => $processId,
            'payment_method' => 'EFECTIVO',
            'raw_ibc_pesos' => 1_750_905,
        ])->assertOk();

        $this->postJson('/api/enrollment/step-6/confirm', [
            'process_id' => $processId,
            'habeas_data_accepted' => false,
        ])->assertStatus(422);
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

    /** RF-005 — tipo de documento fuera del catálogo. */
    public function test_step2_rejects_unknown_document_type(): void
    {
        $step1 = $this->postJson('/api/enrollment/step-1', [
            'client_type' => 'SERVICONLI',
            'contributor_type_code' => '01',
        ]);
        $processId = (int) $step1->json('processId');

        $this->postJson('/api/enrollment/step-2', [
            'process_id' => $processId,
            'document_type' => 'XX',
            'document_number' => '1',
            'first_name' => 'A',
            'first_surname' => 'B',
            'gender' => 'M',
            'address' => 'Calle',
            'cellphone' => '3001112233',
        ])->assertStatus(422);
    }

    /** RF-006 — al menos un teléfono (ninguno enviado). */
    public function test_step2_rejects_when_all_contact_phones_missing(): void
    {
        $step1 = $this->postJson('/api/enrollment/step-1', [
            'client_type' => 'SERVICONLI',
            'contributor_type_code' => '01',
        ]);
        $processId = (int) $step1->json('processId');

        $this->postJson('/api/enrollment/step-2', [
            'process_id' => $processId,
            'document_type' => 'CC',
            'document_number' => '44332211',
            'first_name' => 'María',
            'first_surname' => 'López',
            'gender' => 'F',
            'address' => 'Carrera 10',
        ])->assertStatus(422);
    }

    /** RF-007 — is_foreigner puede persistirse en paso 2. */
    public function test_step2_accepts_is_foreigner(): void
    {
        $step1 = $this->postJson('/api/enrollment/step-1', [
            'client_type' => 'SERVICONLI',
            'contributor_type_code' => '01',
            'is_type_51' => true,
        ]);
        $processId = (int) $step1->json('processId');

        $this->postJson('/api/enrollment/step-2', [
            'process_id' => $processId,
            'document_type' => 'CE',
            'document_number' => 'EXT-991',
            'first_name' => 'Jane',
            'first_surname' => 'Doe',
            'gender' => 'F',
            'address' => 'Calle 5',
            'cellphone' => '3004445566',
            'is_foreigner' => true,
        ])->assertOk()->assertJsonPath('currentStep', 2);

        $p = EnrollmentProcess::query()->findOrFail($processId);
        $this->assertTrue((bool) ($p->step2_payload['is_foreigner'] ?? false));
    }

    private function seedRegulatoryRatesForPila(): void
    {
        $params = [
            ['rates', 'SALUD_TOTAL_PERCENT', '12.5'],
            ['rates', 'PENSION_TOTAL_PERCENT', '16'],
            ['rates', 'ARL_RISK_CLASS_I_PERCENT', '0.522'],
            ['rates', 'ARL_RISK_CLASS_II_PERCENT', '1.044'],
            ['rates', 'ARL_RISK_CLASS_III_PERCENT', '2.436'],
            ['rates', 'ARL_RISK_CLASS_IV_PERCENT', '4.350'],
            ['rates', 'ARL_RISK_CLASS_V_PERCENT', '6.960'],
            ['rates', 'CCF_DEPENDIENTE_PERCENT', '4'],
            ['rates', 'CCF_INDEPENDIENTE_PERCENT', '2'],
            ['mora', 'DAILY_RATE_PERCENT', '0.0833'],
        ];

        foreach ($params as [$category, $key, $value]) {
            RegulatoryParameter::query()->create([
                'category' => $category,
                'key' => $key,
                'value' => $value,
                'data_type' => 'decimal',
                'legal_basis' => 'Test',
                'valid_from' => '2026-01-01',
                'valid_until' => null,
            ]);
        }
    }
}
