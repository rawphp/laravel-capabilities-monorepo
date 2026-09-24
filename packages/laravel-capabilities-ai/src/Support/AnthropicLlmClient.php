<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Rawphp\Capabilities\Contracts\Metrics;
use Rawphp\Capabilities\Contracts\Tracer;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Anthropic Messages API client behind LlmClient.
 * Unit tests use Http::fake — no real network in CI.
 *
 * Multi-round tools: package tool defs → Anthropic tools (input_schema);
 * tool_use blocks → tool_calls with id; role=tool → tool_result content blocks.
 *
 * Observability (D-019): optional core Metrics/Tracer record latency, token
 * usage (response `usage`) and failures around the outbound call only.
 *
 * Rate limits: a 429 is retried up to $maxRetries times via Laravel's Http retry,
 * waiting Retry-After seconds (capped) or 1s, 2s, 4s… when the header is unusable.
 */
final class AnthropicLlmClient implements LlmClient
{
    use LlmClientDefaults;

    public const METRIC_LATENCY = 'capabilities_ai_llm_duration_ms';

    public const METRIC_TOKENS = 'capabilities_ai_llm_tokens_total';

    public const METRIC_FAILURES = 'capabilities_ai_llm_failures_total';

    public const SPAN_COMPLETE = 'capabilities_ai.llm.complete';

    private const MAX_RETRY_AFTER_SECONDS = 60;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-4-6',
        private readonly string $baseUrl = 'https://api.anthropic.com',
        private readonly int $maxTokens = 64000,
        private readonly ?Metrics $metrics = null,
        private readonly ?Tracer $tracer = null,
        private readonly int $maxRetries = 2,
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

            // User (and any non-system/tool/assistant role): pass multimodal block
            // arrays through for vision; stringify pure text only.
            $chat[] = [
                'role' => 'user',
                'content' => $this->userContent($m['content'] ?? ''),
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

        $response = $this->send($payload);

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
                    // Decode Anthropic-safe wire name back to package/capability name.
                    'name' => self::decodeToolName((string) ($block['name'] ?? '')),
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
     * POST /v1/messages wrapped in latency, token and failure telemetry.
     *
     * @param  array<string, mixed>  $payload
     */
    private function send(array $payload): Response
    {
        $labels = ['provider' => 'anthropic', 'model' => $this->model];
        $spanId = $this->tracer?->startSpan(self::SPAN_COMPLETE, $labels);
        $started = hrtime(true);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->retry(
                max(0, $this->maxRetries) + 1,
                fn (int $attempt, Throwable $e): int => $this->retryDelayMs($attempt, $e),
                fn (Throwable $e): bool => $e instanceof RequestException && $e->response->status() === 429,
                throw: false,
            )->post(rtrim($this->baseUrl, '/').'/v1/messages', $payload);
        } catch (Throwable $e) {
            $this->metrics?->histogram(self::METRIC_LATENCY, self::elapsedMs($started), $labels);
            $this->metrics?->increment(self::METRIC_FAILURES, 1, $labels + ['reason' => 'transport']);
            $this->endSpan($spanId, 'error');

            throw $e;
        }

        $this->metrics?->histogram(self::METRIC_LATENCY, self::elapsedMs($started), $labels);

        if (! $response->successful()) {
            $this->metrics?->increment(self::METRIC_FAILURES, 1, $labels + ['reason' => 'http_'.$response->status()]);
            $this->endSpan($spanId, 'error', ['http_status' => $response->status()]);

            return $response;
        }

        $usage = [];
        foreach (['input' => 'input_tokens', 'output' => 'output_tokens'] as $type => $key) {
            $count = data_get($response->json(), 'usage.'.$key);
            if (is_int($count)) {
                $usage[$key] = $count;
                $this->metrics?->increment(self::METRIC_TOKENS, $count, $labels + ['type' => $type]);
            }
        }

        $this->endSpan($spanId, 'ok', $usage);

        return $response;
    }

    /**
     * @param  array<string, scalar|null>  $attributes
     */
    private function endSpan(?string $spanId, string $status, array $attributes = []): void
    {
        if ($this->tracer === null || $spanId === null) {
            return;
        }

        if ($attributes !== []) {
            $this->tracer->setAttributes($spanId, $attributes);
        }
        $this->tracer->endSpan($spanId, $status);
    }

    private static function elapsedMs(int $started): float
    {
        return (hrtime(true) - $started) / 1e6;
    }

    /**
     * Milliseconds to wait before retry $attempt: Retry-After seconds when present
     * (capped), else exponential 1s, 2s, 4s….
     */
    private function retryDelayMs(int $attempt, Throwable $e): int
    {
        $retryAfter = $e instanceof RequestException ? trim($e->response->header('Retry-After')) : '';
        $seconds = ctype_digit($retryAfter) ? (int) $retryAfter : 2 ** ($attempt - 1);

        return min($seconds, self::MAX_RETRY_AFTER_SECONDS) * 1000;
    }

    /**
     * Anthropic custom tool names: ^[a-zA-Z0-9_-]{1,128}$ (no dots).
     * Package/capability names use dotted ids (e.g. pane.list). Encode for the
     * wire and decode on tool_use so TurnRunner still invokes the bus by
     * capability name.
     *
     * Fails closed when the encoding is not reversible (e.g. `a__b` would decode
     * to `a.b`), so a tool_use can never resolve to a different capability than
     * the one advertised.
     *
     * @throws InvalidArgumentException
     */
    public static function encodeToolName(string $name): string
    {
        $wire = str_replace('.', '__', $name);
        if (self::decodeToolName($wire) !== $name) {
            throw new InvalidArgumentException(
                "Tool name [{$name}] cannot be encoded reversibly for Anthropic: avoid '__' and '_' next to '.'."
            );
        }

        return $wire;
    }

    /**
     * Reverse {@see encodeToolName} (double-underscore → dot).
     */
    public static function decodeToolName(string $name): string
    {
        return str_replace('__', '.', $name);
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
                'name' => self::encodeToolName($name),
                'description' => (string) ($tool['description'] ?? ''),
                'input_schema' => $schema,
            ];
        }

        return $mapped;
    }

    /**
     * Map package user content to Anthropic Messages API content.
     *
     * List of blocks (text / image / …) is passed through unchanged so hosts can
     * send vision payloads. Scalars are stringified for the text-only path.
     *
     * @return string|list<array<string, mixed>>
     */
    private function userContent(mixed $content): string|array
    {
        if (is_array($content)) {
            return $content;
        }

        return (string) $content;
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
                    // Re-encode package names when replaying assistant tool_use rounds.
                    'name' => self::encodeToolName((string) ($call['name'] ?? '')),
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
