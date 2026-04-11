<?php

namespace Tests\Feature\E2E;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Flujo vertical: login Sanctum → CRUD API afiliados → ficha 360° → notas → export.
 * Sirve como red de seguridad antes de pruebas E2E en navegador o CI.
 */
final class ServiconliVerticalFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function shouldAuthenticateApi(): bool
    {
        return false;
    }

    public function test_login_affiliates_ficha_notes_export(): void
    {
        $user = User::factory()->create([
            'email' => 'e2e@serviconli.test',
            'password' => Hash::make('e2e-secret'),
        ]);
        $user->assignRole('ADMIN');

        $login = $this->postJson('/api/login', [
            'email' => 'e2e@serviconli.test',
            'password' => 'e2e-secret',
        ]);
        $login->assertOk()->assertJsonPath('tokenType', 'Bearer');
        $token = $login->json('token');
        $this->assertNotEmpty($token);

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'e2e@serviconli.test');

        $create = $this->withToken($token)->postJson('/api/affiliates', [
            'document_number' => 'E2E999888',
            'first_name' => 'Flujo',
            'last_name' => 'Vertical',
            'client_type' => 'SERVICONLI',
        ]);
        $create->assertCreated();
        $affiliateId = (int) $create->json('id');

        $this->withToken($token)->getJson('/api/affiliates')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->withToken($token)->getJson("/api/affiliates/{$affiliateId}/ficha-360")
            ->assertOk()
            ->assertJsonPath('affiliate.id', $affiliateId)
            ->assertJsonStructure([
                'person',
                'beneficiaries',
                'notes',
                'portals',
                'documents',
            ]);

        $this->withToken($token)->postJson("/api/affiliates/{$affiliateId}/notes", [
            'note' => 'Nota E2E',
            'note_type' => 'GENERAL',
        ])->assertCreated();

        $this->withToken($token)->getJson("/api/affiliates/{$affiliateId}/notes")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)->getJson("/api/affiliates/{$affiliateId}/ficha-360")
            ->assertOk()
            ->assertJsonPath('counts.notes', 1);

        $csv = $this->withToken($token)->get('/api/affiliates/export?format=csv');
        $csv->assertOk();
        $raw = $csv->streamedContent();
        $this->assertStringContainsString('nombre_completo', $raw);
        $this->assertStringContainsString('Flujo', $raw);
    }
}
