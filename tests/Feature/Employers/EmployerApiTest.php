<?php

namespace Tests\Feature\Employers;

use App\Modules\Employers\Models\Employer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_with_valid_nit(): void
    {
        $create = $this->postJson('/api/employers', [
            'nit_body' => '900966567',
            'digito_verificacion' => 4,
            'razon_social' => 'Servicio Demo SAS',
            'representante_legal' => 'Ana López',
            'email' => 'ana@example.com',
        ]);

        $create->assertCreated()
            ->assertJsonPath('razonSocial', 'Servicio Demo SAS')
            ->assertJsonPath('representanteLegal', 'Ana López');

        $id = $create->json('id');

        $this->getJson('/api/employers/'.$id)->assertOk()->assertJsonPath('nitBody', '900966567');

        $this->patchJson('/api/employers/'.$id, [
            'razon_social' => 'Servicio Demo SAS Actualizado',
            'city_name' => 'Armenia',
        ])->assertOk()->assertJsonPath('razonSocial', 'Servicio Demo SAS Actualizado');

        $this->deleteJson('/api/employers/'.$id)->assertNoContent();
        $this->assertSame(0, Employer::query()->count());
    }

    public function test_store_rejects_invalid_dv(): void
    {
        $response = $this->postJson('/api/employers', [
            'nit_body' => '900966567',
            'digito_verificacion' => 5,
            'razon_social' => 'X',
        ]);

        $response->assertStatus(422);
    }
}
