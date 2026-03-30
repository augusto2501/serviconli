<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sin usuario Sanctum; ver {@see TestCase::shouldAuthenticateApi}. */
final class ApiExceptionUnauthenticatedTest extends TestCase
{
    use RefreshDatabase;

    protected function shouldAuthenticateApi(): bool
    {
        return false;
    }

    public function test_unauthenticated_json_has_code(): void
    {
        $r = $this->getJson('/api/affiliates');

        $r->assertStatus(401)
            ->assertJsonPath('code', 'AUTHENTICATION');
    }
}
