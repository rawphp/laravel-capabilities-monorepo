<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Illuminate\Support\Facades\Http;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use RuntimeException;
use stdClass;

/**
 * Anthropic Messages API client behind LlmClient.
 * Unit tests use Http::fake — no real network in CI.
 *
 * Multi-round tools: package tool defs → Anthropic tools (input_schema);
 * tool_use blocks → tool_calls with id; role=tool → tool_result content blocks.
 */
final class AnthropicLlmClient implements LlmClient
{
    use LlmClientDefaults;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-4-6',
        private readonly string $baseUrl = 'https://api.anthropic.com',
        private readonly int $maxTokens = 64000,
    ) {}

    public function supportsToolRounds(): bool
    {
        return true;
    }

    public function complete(array $messages, array $tools = []): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY is empty');
        }

        $systemParts = [];
        $chat = [];
        foreach ($messages as $m) {
            if (! is_array($m)) {
                continue;
            }
            $role = (string) ($m['role'] ?? 'user');

            if ($role === 'system') {
                $content = (string) ($m['content'] ?? '');
                if ($content !== '') {
                    $systemParts[] = $content;
                }

                continue;
            }

            if ($role === 'tool') {
                $toolUseId = trim((string) ($m['tool_call_id'] ?? $m['id'] ?? ''));
                if ($toolUseId === '') {
                    $toolUseId = 'tool_call_unknown';
                }
                $resultContent = $m['content'] ?? '';
                if (! is_string($resultContent)) {
                    $resultContent = json_encode($resultContent, JSON_THROW_ON_ERROR);
                }
                $chat[] = [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'tool_result',
                        'tool_use_id' => $toolUseId,
                        'content' => $resultContent,
                    ]],
                ];

                continue;
            }

            if ($role === 'assistant') {
                $chat[] = [
                    'role' => 'assistant',
                    'content' => $this->assistantContentBlocks($m),
                ];

                continue;
            }

            $chat[] = [
                'role' => 'user',
                'content' => (string) ($m['content'] ?? ''),
            ];
        }

        $merged = $this->mergeConsecutiveRoles($chat);

        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => $merged !== [] ? $merged : [['role' => 'user', 'content' => '(empty)']],
        ];
        if ($systemParts !== []) {
            $payload['system'] = implode("\n\n", $systemParts);
        }

        if ($tools !== []) {
            $payload['tools'] = $this->mapTools($tools);
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
            if (! is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
            if (($block['type'] ?? '') === 'tool_use') {
                $toolCalls[] = [
                    'id' => (string) ($block['id'] ?? ''),
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

    /**
     * Map package tool defs ({name, description?, parameters?|input_schema?}) to Anthropic tools.
     *
     * @param  list<array<string, mixed>>  $tools
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    private function mapTools(array $tools): array
    {
        $mapped = [];
        foreach ($tools as $tool) {
            if (! is_array($tool)) {
                continue;
            }
            $name = (string) ($tool['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $schema = $tool['input_schema'] ?? $tool['parameters'] ?? [
                'type' => 'object',
                'properties' => new stdClass,
            ];
            if (! is_array($schema)) {
                $schema = ['type' => 'object', 'properties' => new stdClass];
            }
            if (($schema['type'] ?? null) === null) {
                $schema['type'] = 'object';
            }
            if (! array_key_exists('properties', $schema)) {
                $schema['properties'] = new stdClass;
            } elseif (is_array($schema['properties']) && $schema['properties'] === []) {
                // JSON-encode empty object, not empty array (Anthropic expects object).
                $schema['properties'] = new stdClass;
            }

            $mapped[] = [
                'name' => $name,
                'description' => (string) ($tool['description'] ?? ''),
                'input_schema' => $schema,
            ];
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return list<array<string, mixed>>
     */
    private function assistantContentBlocks(array $message): array
    {
        $blocks = [];
        $text = $message['content'] ?? '';
        if (is_string($text) && $text !== '') {
            $blocks[] = ['type' => 'text', 'text' => $text];
        }

        $toolCalls = $message['tool_calls'] ?? null;
        if (is_array($toolCalls)) {
            foreach ($toolCalls as $call) {
                if (! is_array($call)) {
                    continue;
                }
                $id = trim((string) ($call['id'] ?? ''));
                if ($id === '') {
                    $id = 'tool_call_unknown';
                }
                $input = $call['arguments'] ?? $call['input'] ?? [];
                if (! is_array($input)) {
                    $input = [];
                }
                $blocks[] = [
                    'type' => 'tool_use',
                    'id' => $id,
                    'name' => (string) ($call['name'] ?? ''),
                    // Empty input must encode as {} for Anthropic.
                    'input' => $input === [] ? new stdClass : $input,
                ];
            }
        }

        if ($blocks === []) {
            $blocks[] = ['type' => 'text', 'text' => is_string($text) ? $text : ''];
        }

        return $blocks;
    }

    /**
     * Anthropic requires alternating roles; merge consecutive same-role rows.
     *
     * @param  list<array{role: string, content: mixed}>  $chat
     * @return list<array{role: string, content: mixed}>
     */
    private function mergeConsecutiveRoles(array $chat): array
    {
        $merged = [];
        foreach ($chat as $row) {
            $last = $merged === [] ? null : $merged[array_key_last($merged)];
            if ($last !== null && $last['role'] === $row['role']) {
                $merged[array_key_last($merged)]['content'] = $this->mergeContent(
                    $last['content'],
                    $row['content'],
                );
            } else {
                $merged[] = $row;
            }
        }

        return $merged;
    }

    private function mergeContent(mixed $a, mixed $b): mixed
    {
        if (is_string($a) && is_string($b)) {
            return rtrim($a)."\n\n".$b;
        }

        return array_merge($this->contentToBlocks($a), $this->contentToBlocks($b));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contentToBlocks(mixed $content): array
    {
        if (is_array($content)) {
            return $content;
        }

        $text = (string) $content;

        return $text === '' ? [] : [['type' => 'text', 'text' => $text]];
    }
}
