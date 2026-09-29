<?php

namespace Rawphp\CapabilitiesMessaging\Contracts;

/**
 * Host seam behind ConversationIngress: one agent turn for one chat message (D-007 / D-008).
 *
 * Messaging does not build the agent (laravel/ai stays optional). The host binds this, typically
 * around a laravel/ai agent whose tools come from `Capability::aiTools($profile)`. Unbound, chat
 * messages fail closed: no reply, an error log line, no tools.
 *
 * Tool calls are returned, not executed: messaging invokes each one through the capability bus as
 * `caller: agent` with messaging metadata, the profile guard and per-update idempotency keys.
 */
interface AgentTurn
{
    /**
     * Capability names the agent profile exposes. Tool calls outside this list are refused.
     *
     * @return list<string>
     */
    public function toolNames(string $profile): array;

    /**
     * @param  array{
     *     channel: string,
     *     chat_id: string,
     *     text: string,
     *     user: object,
     *     thread_id: string,
     *     profile: string,
     *     messaging: array<string, mixed>,
     *     tools: list<string>
     * }  $message
     * @return array{text: string, tool_calls?: list<array{name: string, input?: array<string, mixed>}>}
     */
    public function respond(array $message): array;
}
