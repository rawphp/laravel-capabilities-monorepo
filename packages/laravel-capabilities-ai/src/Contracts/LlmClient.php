<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Contracts;

use Rawphp\CapabilitiesAi\Support\LlmClientDefaults;

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
     * @param  list<array{role: string, content: string, tool_call_id?: string, id?: string}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array{
     *     content?: string,
     *     tool_calls?: list<array{id?: string, name?: string, arguments?: mixed, input?: mixed}>
     * }
     *         When `tool_calls` is non-empty, each entry SHOULD include non-empty `id`
     *         (required for multi-round correlation; FakeLlmClient always normalizes it).
     */
    public function complete(array $messages, array $tools = []): array;
}
