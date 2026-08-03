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
 */
interface LlmClient
{
    /**
     * Whether this client can continue a conversation after tool results are appended.
     *
     * Fail closed: package MVS expects false unless the client opts into multi-round tools.
     * PHP interfaces cannot supply a method body — host implementors may use
     * {@see LlmClientDefaults} for `return false`.
     */
    public function supportsToolRounds(): bool;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array{content?: string, tool_calls?: list<array<string, mixed>>}
     */
    public function complete(array $messages, array $tools = []): array;
}
