<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiProbeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $apiKey = config('services.anthropic.key');
        $model = config('services.anthropic.model', 'claude-3-5-haiku-latest');
        $prompt = trim((string) $request->query('prompt', 'Responde en una sola frase: la integración con IA está funcionando.'));

        if ($apiKey === null || $apiKey === '') {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró ANTHROPIC_API_KEY (o CLAUDE_API_KEY) en el entorno.',
            ], 500);
        }

        $startedAt = microtime(true);

        $response = Http::timeout(30)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 180,
                'temperature' => 0.2,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return response()->json([
                'ok' => false,
                'message' => 'Anthropic respondió con error.',
                'anthropic_status' => $response->status(),
                'anthropic_error' => $response->json('error') ?? null,
                'anthropic_body' => $response->body(),
            ], 200);
        }

        $content = $response->json('content', []);
        $firstBlock = is_array($content) ? ($content[0] ?? []) : [];
        $text = is_array($firstBlock) ? ($firstBlock['text'] ?? null) : null;

        return response()->json([
            'ok' => true,
            'model' => $model,
            'prompt' => $prompt,
            'answer' => $text,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'usage' => [
                'input_tokens' => $response->json('usage.input_tokens'),
                'output_tokens' => $response->json('usage.output_tokens'),
            ],
        ]);
    }
}
