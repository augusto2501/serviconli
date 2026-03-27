<?php

namespace Tests\Feature\Affiliates;

use App\Modules\Affiliates\Models\Affiliate;
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

        $this->assertDatabaseCount('affiliates', 0);
    }

    public function test_store_rejects_duplicate_document_number(): void
    {
        Affiliate::query()->create([
            'document_number' => '111',
            'first_name' => 'A',
            'last_name' => 'B',
        ]);

        $response = $this->postJson('/api/affiliates', [
            'document_number' => '111',
            'first_name' => 'C',
        ]);

        $response->assertStatus(422);
    }
}
