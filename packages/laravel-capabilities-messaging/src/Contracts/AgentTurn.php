<?php

namespace Rawphp\CapabilitiesMessaging\Contracts;

use Rawphp\Capabilities\Support\CapabilityResult;

/**
 * Host seam behind ConversationIngress: one agent turn for one chat message (D-007 / D-008).
 *
 * Messaging does not build the agent (laravel/ai stays optional). The host binds this, typically
 * around a laravel/ai agent whose tools come from `Capability::aiTools($profile)`. Unbound, chat
 * messages fail closed: no reply, an error log line, no tools.
 *
 * Tool calls are returned, not executed: messaging invokes each one through the capability bus as
 * `caller: agent` with messaging metadata, the profile guard and per-update idempotency keys, then
 * hands the results to {@see respondWithResults()}, whose text is the reply. One tool round per
 * message: invocation stops at the first result that is not ok, and tool calls returned from
 * respondWithResults() are ignored.
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

    /**
     * Answer the results of the tool calls respond() returned. Called only when there were tool
     * calls. Each result is the bus outcome: output (`isOk()`, `data`), `isApprovalRequired()` with
     * `approvalId()`, or a refusal / transient failure (`errorCode()`, `isRetryable()`). Tell the
     * user what happened; a transient failure is not retried for them.
     *
     * @param  array<string, mixed>  $message  the same message respond() received
     * @param  list<array{name: string, input: array<string, mixed>, result: CapabilityResult}>  $toolResults
     * @return array{text: string}
     */
    public function respondWithResults(array $message, array $toolResults): array;
}
