<?php

namespace Tests\Feature\Disabilities;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\RegulatoryEngine\Models\DiagnosisCie10;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_and_list_disability_with_cie10(): void
    {
        $cie = DiagnosisCie10::query()->create([
            'code' => 'J06.9',
            'description' => 'Infección aguda vías altas',
        ]);

        $person = Person::query()->create([
            'document_number' => '112233',
            'first_name' => 'Luis',
            'first_surname' => 'Pérez',
            'gender' => 'M',
            'address' => 'Calle 2',
        ]);

        $affiliate = Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ]);

        $r = $this->postJson('/api/affiliates/'.$affiliate->id.'/disabilities', [
            'source' => 'EPS_GENERAL',
            'subtype_code' => 'EG_01',
            'diagnosis_cie10_id' => $cie->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-10',
            'submitted_documents' => ['cert_medico'],
        ]);

        $r->assertCreated()
            ->assertJsonPath('cumulativeDays', 10)
            ->assertJsonPath('over180Alert', false);

        $this->getJson('/api/affiliates/'.$affiliate->id.'/disabilities')->assertOk()->assertJsonPath('data.0.source', 'EPS_GENERAL');
    }
}
