<?php

namespace Tests\Feature\Affiliates;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Affiliations\Models\AffiliatePayer;
use App\Modules\Affiliations\Models\Payer;
use App\Modules\Affiliations\Models\SocialSecurityProfile;
use App\Modules\RegulatoryEngine\Models\SSEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateListingFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_includes_rf021_fields(): void
    {
        $person = Person::query()->create([
            'document_number' => '555',
            'first_name' => 'Ana',
            'second_name' => 'María',
            'first_surname' => 'Gómez',
        ]);
        $affiliate = Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
            'mora_status' => 'AL_DIA',
        ]);

        $r = $this->getJson('/api/affiliates');
        $r->assertOk()
            ->assertJsonPath('data.0.fullName', 'Ana María Gómez')
            ->assertJsonPath('data.0.paymentIndicator', 'SI')
            ->assertJsonPath('data.0.documentNumber', '555');
    }

    public function test_filter_payments_on_track_no(): void
    {
        $p1 = Person::query()->create(['document_number' => '1', 'first_name' => 'A', 'first_surname' => 'B']);
        $p2 = Person::query()->create(['document_number' => '2', 'first_name' => 'C', 'first_surname' => 'D']);
        Affiliate::query()->create(['person_id' => $p1->id, 'client_type' => AffiliateClientType::SERVICONLI, 'mora_status' => 'MORA_30']);
        Affiliate::query()->create(['person_id' => $p2->id, 'client_type' => AffiliateClientType::SERVICONLI, 'mora_status' => 'AL_DIA']);

        $this->getJson('/api/affiliates?payments_on_track=no')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_filter_eps_entity_id(): void
    {
        $eps = SSEntity::query()->create([
            'pila_code' => 'EPSF',
            'name' => 'EPS Filtro',
            'type' => 'EPS',
            'status' => 'ACTIVE',
        ]);

        $p1 = Person::query()->create(['document_number' => '10', 'first_name' => 'A', 'first_surname' => 'B']);
        $p2 = Person::query()->create(['document_number' => '11', 'first_name' => 'C', 'first_surname' => 'D']);
        $a1 = Affiliate::query()->create(['person_id' => $p1->id, 'client_type' => AffiliateClientType::SERVICONLI]);
        $a2 = Affiliate::query()->create(['person_id' => $p2->id, 'client_type' => AffiliateClientType::SERVICONLI]);

        SocialSecurityProfile::query()->create([
            'affiliate_id' => $a1->id,
            'eps_entity_id' => $eps->id,
            'valid_from' => '2026-01-01',
            'valid_until' => null,
        ]);
        $this->getJson('/api/affiliates?eps_entity_id='.$eps->id)->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_filter_pila_operator_code(): void
    {
        $payer = Payer::query()->create([
            'nit' => '900',
            'razon_social' => 'Pagador',
            'pila_operator_code' => 'ARUS',
        ]);

        $p1 = Person::query()->create(['document_number' => '20', 'first_name' => 'A', 'first_surname' => 'B']);
        $p2 = Person::query()->create(['document_number' => '21', 'first_name' => 'C', 'first_surname' => 'D']);
        $a1 = Affiliate::query()->create(['person_id' => $p1->id, 'client_type' => AffiliateClientType::SERVICONLI]);
        $a2 = Affiliate::query()->create(['person_id' => $p2->id, 'client_type' => AffiliateClientType::SERVICONLI]);

        AffiliatePayer::query()->create([
            'affiliate_id' => $a1->id,
            'payer_id' => $payer->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $this->getJson('/api/affiliates?pila_operator_code=ARUS')->assertOk()->assertJsonPath('meta.total', 1);
    }
}
