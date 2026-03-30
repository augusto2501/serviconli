<?php

namespace Tests\Feature\Affiliates;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Affiliates\Models\PortalCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalCredentialApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedAffiliate(string $documentNumber = '9001'): Affiliate
    {
        $person = Person::query()->create([
            'document_number' => $documentNumber,
            'first_name' => 'Luis',
            'first_surname' => 'Pérez',
        ]);

        return Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ]);
    }

    public function test_crud_and_ficha_360_includes_portals(): void
    {
        $a = $this->seedAffiliate();

        $this->getJson('/api/affiliates/'.$a->id.'/portal-credentials')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson('/api/affiliates/'.$a->id.'/portal-credentials', [
            'portal_type' => 'EPS',
            'username' => 'usr_eps',
            'password' => 'secret_eps',
        ])->assertCreated()
            ->assertJsonPath('portalType', 'EPS')
            ->assertJsonPath('password', 'secret_eps');

        $this->getJson('/api/affiliates/'.$a->id.'/portal-credentials')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'usr_eps');

        $id = PortalCredential::query()->where('affiliate_id', $a->id)->value('id');
        $this->assertNotNull($id);

        $this->patchJson('/api/affiliates/'.$a->id.'/portal-credentials/'.$id, [
            'notes' => 'Nota interna',
        ])->assertOk()
            ->assertJsonPath('notes', 'Nota interna')
            ->assertJsonPath('password', 'secret_eps');

        $ficha = $this->getJson('/api/affiliates/'.$a->id.'/ficha-360');
        $ficha->assertOk()
            ->assertJsonPath('portals.available', true)
            ->assertJsonPath('portals.encryptionEnabled', false)
            ->assertJsonCount(1, 'portals.items')
            ->assertJsonPath('portals.items.0.portalType', 'EPS')
            ->assertJsonPath('portals.items.0.password', 'secret_eps');

        $this->deleteJson('/api/affiliates/'.$a->id.'/portal-credentials/'.$id)
            ->assertNoContent();

        $this->assertSame(0, PortalCredential::query()->where('affiliate_id', $a->id)->count());
    }

    public function test_update_other_affiliate_portal_returns_404(): void
    {
        $a1 = $this->seedAffiliate('9002');
        $a2 = $this->seedAffiliate('9003');

        $this->postJson('/api/affiliates/'.$a1->id.'/portal-credentials', [
            'portal_type' => 'ARL',
            'username' => 'x',
        ])->assertCreated();

        $pid = PortalCredential::query()->where('affiliate_id', $a1->id)->value('id');

        $this->patchJson('/api/affiliates/'.$a2->id.'/portal-credentials/'.$pid, [
            'username' => 'y',
        ])->assertNotFound();
    }
}
