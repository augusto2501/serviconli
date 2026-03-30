<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProbeTest extends TestCase
{
    protected function shouldAuthenticateApi(): bool
    {
        return false;
    }

    public function test_ai_probe_returns_claude_answer_when_upstream_is_ok(): void
    {
        config()->set('services.anthropic.key', 'test-key');
        config()->set('services.anthropic.model', 'claude-test-model');

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'IA funcionando correctamente.'],
                ],
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 7,
                ],
            ], 200),
        ]);

        $response = $this->get('/ai/probe?prompt=Prueba%20de%20IA');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('model', 'claude-test-model')
            ->assertJsonPath('answer', 'IA funcionando correctamente.')
            ->assertJsonPath('usage.input_tokens', 10);
    }

    public function test_ai_probe_fails_when_key_is_missing(): void
    {
        config()->set('services.anthropic.key', '');

        $response = $this->get('/ai/probe');

        $response->assertStatus(500)
            ->assertJsonPath('ok', false);
    }
}
