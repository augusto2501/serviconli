<?php

namespace Tests\Feature\CashReconciliation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashReconciliationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_none_without_reconciliation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/cash-reconciliation?date='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('status', 'NONE');
    }

    public function test_recalculate_creates_reconciliation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/cash-reconciliation/recalculate', [
            'date' => now()->toDateString(),
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ABIERTO');
    }
}
