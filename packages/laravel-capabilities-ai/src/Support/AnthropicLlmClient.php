<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Rawphp\Capabilities\Contracts\Metrics;
use Rawphp\Capabilities\Contracts\Tracer;
use Rawphp\CapabilitiesAi\Contracts\DeadlineAwareLlmClient;
use Rawphp\CapabilitiesAi\Package;
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
 * Deadline: $deadlineSeconds after the call started, or the turn deadline TurnRunner
 * passes via withDeadline() when that is sooner. Each request's transport timeout is
 * $timeoutSeconds capped at the time left minus DEADLINE_MARGIN_SECONDS, and no request
 * starts with under MIN_REQUEST_SECONDS left (RetryableLlmException, nothing sent).
 *
 * Rate limits: a 429 is retried up to $maxRetries times via Laravel's Http retry,
 * waiting Retry-After seconds (capped) or 1s, 2s, 4s… when the header is unusable.
 * The retry follows the same rule, measured from when it will start: it gets the
 * capped timeout, or is skipped when under MIN_REQUEST_SECONDS would be left. The
 * 429 then surfaces as a RetryableLlmException carrying its Retry-After.
 */
final class AnthropicLlmClient implements DeadlineAwareLlmClient
{
    use LlmClientDefaults;

    public const METRIC_LATENCY = 'capabilities_ai_llm_duration_ms';

    public const METRIC_TOKENS = 'capabilities_ai_llm_tokens_total';

    public const METRIC_FAILURES = 'capabilities_ai_llm_failures_total';

    public const SPAN_COMPLETE = 'capabilities_ai.llm.complete';

    private const MAX_RETRY_AFTER_SECONDS = 60;

    /** Per-request transport timeout; below the default claim_ttl (120s) so one call fits a turn job. */
    public const DEFAULT_TIMEOUT_SECONDS = 110;

    /**
     * Seconds kept between a request's capped transport timeout and the deadline. The job
     * timer starts slightly before TurnRunner fixes the deadline, and after a timeout the
     * worker still has to record the failed turn (status row, progress events) before the
     * job is killed; both take well under 2s.
     */
    private const DEADLINE_MARGIN_SECONDS = 2;

    /** Absolute turn deadline (hrtime ns) from withDeadline(); null = per-call deadline only. */
    private ?int $turnDeadlineNs = null;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-4-6',
        private readonly string $baseUrl = 'https://api.anthropic.com',
        private readonly int $maxTokens = 64000,
        private readonly ?Metrics $metrics = null,
        private readonly ?Tracer $tracer = null,
        private readonly int $maxRetries = 2,
        private readonly int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
        /** Whole complete() call, 429 waits included, must end this long after it starts (claim_ttl). */
        private readonly int $deadlineSeconds = Package::DEFAULT_CLAIM_TTL,
    ) {
        if ($this->timeoutSeconds <= 0) {
            throw new InvalidArgumentException('Anthropic timeout must be a positive number of seconds');
        }
    }

    public function supportsToolRounds(): bool
    {
        return true;
    }

    public function withDeadline(int $deadlineNs): static
    {
        $copy = clone $this;
        $copy->turnDeadlineNs = $deadlineNs;

        return $copy;
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

        try {
            $response = $this->send($payload);
        } catch (ConnectionException $e) {
            throw new RetryableLlmException('Anthropic API connection error: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            $detail = '';
            $json = $response->json();
            if (is_array($json)) {
                $detail = (string) data_get($json, 'error.message', '');
            }
            if ($detail === '') {
                $detail = substr($response->body(), 0, 200);
            }
            $status = $response->status();
            $message = 'Anthropic API error: '.$status.($detail !== '' ? ' ('.$detail.')' : '');
            // Timeout, lock conflict, rate limit, and server/overload (529) errors may clear on re-drive.
            if (in_array($status, [408, 409, 429], true) || $status >= 500) {
                $retryAfter = trim($response->header('Retry-After'));
                throw new RetryableLlmException(
                    $message,
                    status: $status,
                    retryAfterSeconds: ctype_digit($retryAfter) ? (int) $retryAfter : null,
                );
            }
            throw new RuntimeException($message);
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
        if (is_array($json['usage'] ?? null)) {
            $out['usage'] = [
                'input_tokens' => (int) ($json['usage']['input_tokens'] ?? 0),
                'output_tokens' => (int) ($json['usage']['output_tokens'] ?? 0),
            ];
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
        $started = hrtime(true);
        $deadlineNs = $started + $this->deadlineSeconds * 1_000_000_000;
        if ($this->turnDeadlineNs !== null) {
            $deadlineNs = min($deadlineNs, $this->turnDeadlineNs);
        }
        $timeout = $this->timeoutFor($started, $deadlineNs)
            ?? throw new RetryableLlmException(
                'Anthropic request not sent: under '.self::MIN_REQUEST_SECONDS.'s left before the turn deadline'
            );
        $spanId = $this->tracer?->startSpan(self::SPAN_COMPLETE, $labels);
        $failedAttempts = 0;

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout($timeout)->retry(
                max(0, $this->maxRetries) + 1,
                fn (int $attempt, Throwable $e): int => $this->retryDelayMs($attempt, $e),
                function (Throwable $e, PendingRequest $request) use ($deadlineNs, &$failedAttempts): bool {
                    if (! $e instanceof RequestException || $e->response->status() !== 429) {
                        return false;
                    }
                    $retryAt = hrtime(true) + $this->retryDelayMs(++$failedAttempts, $e) * 1_000_000;
                    $retryTimeout = $this->timeoutFor($retryAt, $deadlineNs);
                    if ($retryTimeout === null) {
                        return false;
                    }
                    // Options are re-read per attempt, so the retry runs with its own cap.
                    $request->timeout($retryTimeout);

                    return true;
                },
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
     * Transport timeout for a request starting at $startNs: the configured timeout, capped
     * so the request ends DEADLINE_MARGIN_SECONDS before $deadlineNs; null when under
     * MIN_REQUEST_SECONDS would be left, so the request is not worth starting.
     */
    private function timeoutFor(int $startNs, int $deadlineNs): ?int
    {
        $leftNs = $deadlineNs - $startNs;
        if ($leftNs < self::MIN_REQUEST_SECONDS * 1_000_000_000) {
            return null;
        }

        return min($this->timeoutSeconds, intdiv($leftNs, 1_000_000_000) - self::DEADLINE_MARGIN_SECONDS);
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
