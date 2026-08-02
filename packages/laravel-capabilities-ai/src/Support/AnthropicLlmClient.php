<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Illuminate\Support\Facades\Http;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use RuntimeException;

/**
 * Anthropic Messages API client behind LlmClient.
 * Unit tests use Http::fake — no real network in CI.
 */
final class AnthropicLlmClient implements LlmClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-4-20250514',
        private readonly string $baseUrl = 'https://api.anthropic.com',
    ) {}

    public function complete(array $messages, array $tools = []): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY is empty');
        }

        $payload = [
            'model' => $this->model,
            'max_tokens' => 1024,
            'messages' => array_values(array_map(static function (array $m): array {
                return [
                    'role' => $m['role'] === 'assistant' ? 'assistant' : 'user',
                    'content' => $m['content'],
                ];
            }, $messages)),
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post(rtrim($this->baseUrl, '/').'/v1/messages', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Anthropic API error: '.$response->status());
        }

        $json = $response->json();
        $text = '';
        $toolCalls = [];
        foreach ($json['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
            if (($block['type'] ?? '') === 'tool_use') {
                $toolCalls[] = [
                    'name' => (string) ($block['name'] ?? ''),
                    'arguments' => $block['input'] ?? [],
                ];
            }
        }

        $out = ['content' => $text];
        if ($toolCalls !== []) {
            $out['tool_calls'] = $toolCalls;
        }

        return $out;
    }
}
