<?php

namespace Tests\Feature\Affiliates;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_empty_data(): void
    {
        $response = $this->getJson('/api/affiliates');

        $response->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_store_show_update_destroy_flow(): void
    {
        $create = $this->postJson('/api/affiliates', [
            'document_number' => '1234567890',
            'first_name' => 'María',
            'last_name' => 'López',
        ]);

        $create->assertCreated()
            ->assertJsonPath('documentNumber', '1234567890')
            ->assertJsonPath('firstName', 'María');

        $id = $create->json('id');

        $show = $this->getJson('/api/affiliates/'.$id);
        $show->assertOk()->assertJsonPath('documentNumber', '1234567890');

        $update = $this->patchJson('/api/affiliates/'.$id, [
            'first_name' => 'María Elena',
        ]);
        $update->assertOk()->assertJsonPath('firstName', 'María Elena');

        $index = $this->getJson('/api/affiliates?q=López');
        $index->assertOk()->assertJsonPath('meta.total', 1);

        $delete = $this->deleteJson('/api/affiliates/'.$id);
        $delete->assertNoContent();

        $this->assertSame(0, Affiliate::query()->count());
        $this->assertSame(0, Person::query()->count());
        $this->getJson('/api/affiliates/'.$id)->assertNotFound();
    }

    public function test_store_rejects_duplicate_document_number(): void
    {
        $person = Person::query()->create([
            'document_number' => '111',
            'first_name' => 'A',
            'first_surname' => 'B',
        ]);
        Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ]);

        $response = $this->postJson('/api/affiliates', [
            'document_number' => '111',
            'first_name' => 'C',
        ]);

        $response->assertStatus(422);
    }
}
