<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Contracts;

use Rawphp\CapabilitiesAi\Support\LlmClientDefaults;
use Rawphp\CapabilitiesAi\Support\RetryableLlmException;

/**
 * Pluggable LLM client. Hosts may resolve without a Conversation (MVS jobs).
 *
 * Multi-round tools: only clients that can accept tool results after tool_use
 * (or OpenAI-style role=tool messages) should report supportsToolRounds() = true.
 * TurnRunner refuses bus invokes when false so half-wired clients cannot mutate
 * product state then crash on the follow-up complete() call.
 *
 * Tool message contract (when supportsToolRounds() is true):
 * - Each entry in returned `tool_calls[]` MUST include a non-empty string `id`
 *   (provider tool_use id or a stable client-generated id).
 * - TurnRunner appends follow-up messages with `role=tool`, `content` (JSON result),
 *   and matching `tool_call_id` (+ `id`) for each invoke so providers can correlate
 *   tool_result blocks with the original tool_use / tool_call.
 * - A response may carry `raw_content`: the provider's content blocks exactly as received.
 *   TurnRunner keeps it on the assistant message it appends for the next round, so the client
 *   can send those blocks back unchanged (Anthropic server tool blocks such as tool search
 *   must be replayed in order). Without it the client rebuilds from `content` + `tool_calls`.
 *
 * Failures: throw {@see RetryableLlmException} for transient provider errors (rate limit,
 * overload, 5xx, connection) once the client's own retries are spent. TurnRunner still fails
 * the turn but flags its progress `error` event `retryable: true` so callers can try again
 * instead of treating it as dead. Any other throwable is permanent (`retryable: false`).
 */
interface LlmClient
{
    /**
     * Whether this client can continue a conversation after tool results are appended.
     *
     * Fail closed: package MVS expects false unless the client opts into multi-round tools.
     * PHP interfaces still cannot supply method bodies on supported PHP — host implementors
     * may use {@see LlmClientDefaults} for `return false`.
     */
    public function supportsToolRounds(): bool;

    /**
     * @param  list<array{
     *     role: string,
     *     content: string|list<array<string, mixed>>,
     *     tool_call_id?: string,
     *     id?: string,
     *     tool_calls?: list<array<string, mixed>>,
     *     raw_content?: list<mixed>
     * }>  $messages
     *     User/assistant `content` may be a plain string or a list of provider content
     *     blocks (e.g. Anthropic text + image base64). Hosts hydrate image bytes into
     *     context; this package does not store or fetch attachments. Tool messages still
     *     use string (or JSON-encoded) content with `tool_call_id` / `id` correlation.
     * @param  list<array<string, mixed>>  $tools
     * @return array{
     *     content?: string,
     *     tool_calls?: list<array{id?: string, name?: string, arguments?: mixed, input?: mixed}>,
     *     usage?: array{input_tokens?: int, output_tokens?: int, cache_creation_input_tokens?: int, cache_read_input_tokens?: int},
     *     raw_content?: list<mixed>
     * }
     *         When `tool_calls` is non-empty, each entry SHOULD include non-empty `id`
     *         (required for multi-round correlation; FakeLlmClient always normalizes it).
     *         `usage` is optional provider token accounting for this call; TurnRunner
     *         stores it per round on the Turn alongside its own measured `latency_ms`.
     *
     * @throws RetryableLlmException transient provider failure (caller may try again later)
     */
    public function complete(array $messages, array $tools = []): array;
}
