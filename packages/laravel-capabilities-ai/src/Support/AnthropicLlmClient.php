<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Illuminate\Support\Facades\Http;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use RuntimeException;

/**
 * Anthropic Messages API client behind LlmClient.
 * Unit tests use Http::fake — no real network in CI.
 *
 * Multi-round tools stay off via LlmClientDefaults until true tool_result blocks ship.
 */
final class AnthropicLlmClient implements LlmClient
{
    use LlmClientDefaults;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-4-6',
        private readonly string $baseUrl = 'https://api.anthropic.com',
    ) {}

    public function complete(array $messages, array $tools = []): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY is empty');
        }

        $systemParts = [];
        $chat = [];
        foreach ($messages as $m) {
            $role = (string) ($m['role'] ?? 'user');
            $content = (string) ($m['content'] ?? '');
            if ($role === 'system') {
                if ($content !== '') {
                    $systemParts[] = $content;
                }

                continue;
            }
            if ($role === 'tool') {
                throw new RuntimeException(
                    'AnthropicLlmClient does not support role=tool messages yet; structured tool_result blocks are not implemented (fail closed)'
                );
            }
            $chat[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $content,
            ];
        }

        // Anthropic requires alternating roles; merge consecutive same-role rows.
        $merged = [];
        foreach ($chat as $row) {
            $last = $merged === [] ? null : $merged[array_key_last($merged)];
            if ($last !== null && $last['role'] === $row['role']) {
                $merged[array_key_last($merged)]['content'] =
                    rtrim((string) $last['content'])."\n\n".$row['content'];
            } else {
                $merged[] = $row;
            }
        }

        $payload = [
            'model' => $this->model,
            'max_tokens' => 1024,
            'messages' => $merged !== [] ? $merged : [['role' => 'user', 'content' => '(empty)']],
        ];
        if ($systemParts !== []) {
            $payload['system'] = implode("\n\n", $systemParts);
        }

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post(rtrim($this->baseUrl, '/').'/v1/messages', $payload);

        if (! $response->successful()) {
            $detail = '';
            $json = $response->json();
            if (is_array($json)) {
                $detail = (string) data_get($json, 'error.message', '');
            }
            if ($detail === '') {
                $detail = substr($response->body(), 0, 200);
            }
            throw new RuntimeException(
                'Anthropic API error: '.$response->status()
                .($detail !== '' ? ' ('.$detail.')' : '')
            );
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
