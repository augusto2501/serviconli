<?php

namespace Tests\Feature\Affiliates;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Affiliations\Models\SocialSecurityProfile;
use App\Modules\RegulatoryEngine\Models\SSEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_csv_streams_utf8_bom_headers(): void
    {
        $person = Person::query()->create([
            'document_number' => '123',
            'first_name' => 'Juan',
            'first_surname' => 'Pérez',
        ]);
        $affiliate = Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
            'mora_status' => 'AL_DIA',
        ]);

        $eps = SSEntity::query()->create([
            'pila_code' => 'EPSX',
            'name' => 'EPS Demo',
            'type' => 'EPS',
            'status' => 'ACTIVE',
        ]);

        SocialSecurityProfile::query()->create([
            'affiliate_id' => $affiliate->id,
            'eps_entity_id' => $eps->id,
            'valid_from' => '2026-01-01',
            'valid_until' => null,
        ]);

        $response = $this->get('/api/affiliates/export?format=csv');

        $response->assertOk();
        $this->assertStringContainsString('export-afiliados-', $response->headers->get('Content-Disposition') ?? '');
        $raw = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $raw);
        $this->assertStringContainsString('primer_nombre', $raw);
        $this->assertStringContainsString('Juan', $raw);
        $this->assertStringContainsString('AL_DIA', $raw);
        $this->assertStringContainsString('eps', $raw);
        $this->assertStringContainsString('EPS Demo', $raw);
    }

    public function test_export_rejects_unknown_format(): void
    {
        $this->getJson('/api/affiliates/export?format=xlsx')->assertStatus(400);
    }
}
