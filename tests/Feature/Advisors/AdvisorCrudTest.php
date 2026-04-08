<?php

namespace Tests\Feature\Advisors;

use App\Modules\Advisors\Models\Advisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvisorCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_list_show_update_delete_advisor(): void
    {
        $create = $this->postJson('/api/advisors', [
            'code' => 'AS-001',
            'document_type' => 'CC',
            'document_number' => '123456',
            'first_name' => 'Patricia',
            'last_name' => 'López',
            'phone' => '3001112233',
            'email' => 'p@example.com',
            'commission_new' => 50_000,
            'commission_recurring' => 10_000,
            'authorizes_credits' => true,
        ]);
        $create->assertCreated()->assertJsonPath('code', 'AS-001')->assertJsonPath('authorizesCredits', true);

        $id = (int) $create->json('id');

        $this->getJson('/api/advisors')->assertOk()->assertJsonPath('meta.total', 1);

        $this->getJson('/api/advisors/'.$id)->assertOk()->assertJsonPath('firstName', 'Patricia');

        $this->putJson('/api/advisors/'.$id, [
            'first_name' => 'Patricia María',
            'commission_new' => 60_000,
        ])->assertOk()->assertJsonPath('firstName', 'Patricia María')->assertJsonPath('commissionNew', 60_000);

        $this->deleteJson('/api/advisors/'.$id)->assertNoContent();

        $this->assertSame(0, Advisor::query()->count());
    }
}
