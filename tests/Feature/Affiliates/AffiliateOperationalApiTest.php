<?php

namespace Tests\Feature\Affiliates;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateOperationalApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedAffiliate(): Affiliate
    {
        $person = Person::query()->create([
            'document_number' => '8001',
            'first_name' => 'Ana',
            'first_surname' => 'Ruiz',
        ]);

        return Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ]);
    }

    public function test_ficha_360_returns_counts_and_person(): void
    {
        $a = $this->seedAffiliate();

        $r = $this->getJson('/api/affiliates/'.$a->id.'/ficha-360');
        $r->assertOk()
            ->assertJsonStructure([
                'affiliate',
                'person',
                'socialSecurity',
                'payer',
                'counts',
                'beneficiaries',
                'notes',
                'contributions',
                'invoices',
                'operationalExceptions',
                'portals',
                'documents',
            ])
            ->assertJsonPath('person.documentNumber', '8001')
            ->assertJsonPath('counts.beneficiaries', 0)
            ->assertJsonPath('counts.notes', 0);
    }

    public function test_beneficiaries_and_notes_flow(): void
    {
        $a = $this->seedAffiliate();

        $this->postJson('/api/affiliates/'.$a->id.'/beneficiaries', [
            'document_number' => '8002',
            'first_name' => 'Luis',
            'parentesco' => 'HIJO',
        ])->assertCreated();

        $this->postJson('/api/affiliates/'.$a->id.'/notes', [
            'note' => 'Seguimiento',
            'note_type' => 'GENERAL',
        ])->assertCreated();

        $this->getJson('/api/affiliates/'.$a->id.'/beneficiaries')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/affiliates/'.$a->id.'/notes')->assertOk()->assertJsonCount(1, 'data');

        $ficha = $this->getJson('/api/affiliates/'.$a->id.'/ficha-360');
        $ficha->assertJsonPath('counts.beneficiaries', 1)
            ->assertJsonPath('counts.notes', 1);
    }
}
