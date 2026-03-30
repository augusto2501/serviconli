<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiExceptionHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_error_includes_code_and_errors(): void
    {
        $r = $this->postJson('/api/login', []);

        $r->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['message', 'code', 'errors']);
    }

    public function test_not_found_json_has_code(): void
    {
        $r = $this->getJson('/api/affiliates/999999');

        $r->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }
}
