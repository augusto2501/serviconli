<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function shouldAuthenticateApi(): bool
    {
        return false;
    }

    public function test_login_returns_token_and_user(): void
    {
        $user = User::factory()->create([
            'email' => 'ops@serviconli.test',
            'password' => Hash::make('secret-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'ops@serviconli.test',
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'tokenType', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('user.email', 'ops@serviconli.test');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'x@y.z',
            'password' => Hash::make('good'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'x@y.z',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_protected_route_returns_401_without_token(): void
    {
        $this->getJson('/api/affiliates')->assertStatus(401);
    }

    public function test_user_endpoint_returns_profile_with_bearer_token(): void
    {
        $user = User::factory()->create(['email' => 'me@test.com']);

        $token = $user->createToken('test')->plainTextToken;

        $this->getJson('/api/user', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()->assertJsonPath('email', 'me@test.com');
    }
}
