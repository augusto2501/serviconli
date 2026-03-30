<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Navegador sin Accept: application/json (document) debe recibir 401 JSON en /api/*.
 */
final class ApiBrowserUnauthenticatedTest extends TestCase
{
    use RefreshDatabase;

    protected function shouldAuthenticateApi(): bool
    {
        return false;
    }

    public function test_api_path_returns_json_401_without_accept_json_header(): void
    {
        $r = $this->call('GET', '/api/affiliates', [], [], [], [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        ]);

        $r->assertStatus(401)
            ->assertJsonPath('code', 'AUTHENTICATION');
    }
}
