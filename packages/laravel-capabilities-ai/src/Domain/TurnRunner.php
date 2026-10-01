<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\Redactor;
use Rawphp\CapabilitiesAi\Contracts\ConversationContextProvider;
use Rawphp\CapabilitiesAi\Contracts\ConversationStore;
use Rawphp\CapabilitiesAi\Contracts\DeadlineAwareLlmClient;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Contracts\TurnClaim;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Package;
use Rawphp\CapabilitiesAi\Support\EloquentConversationStore;
use Rawphp\CapabilitiesAi\Support\ProposalFenceExtractor;
use Rawphp\CapabilitiesAi\Support\ResolveConversationActor;
use Rawphp\CapabilitiesAi\Support\RetryableLlmException;
use Rawphp\CapabilitiesAi\Support\ToolSchemaHash;
use RuntimeException;

/**
 * Run a claimed turn: LLM loop + bus-only tool invokes.
 *
 * Turn budget: the whole turn runs in one job whose timeout is claim_ttl ($turnBudgetSeconds).
 * A {@see DeadlineAwareLlmClient} gets that deadline and caps each request (and retry) to the
 * time left, so the worker is never killed mid-request. A round is refused, failing the turn
 * as retryable, only when less than {@see DeadlineAwareLlmClient::MIN_REQUEST_SECONDS} remain.
 */
final class TurnRunner
{
    public function __construct(
        private readonly TurnClaim $claim,
        private readonly LlmClient $llm,
        private readonly ProgressStore $progress,
        private readonly ?ConversationContextProvider $context = null,
        private readonly ?ToolCatalog $tools = null,
        private readonly ?CapabilityBus $bus = null,
        private readonly int $maxToolRounds = 8,
        private readonly string $claimOwner = 'turn-runner',
        private readonly ProposalFenceExtractor $proposalExtractor = new ProposalFenceExtractor,
        private readonly ResolveConversationActor $actors = new ResolveConversationActor,
        private readonly bool $proposalsEnabled = true,
        private readonly ConversationStore $store = new EloquentConversationStore,
        private readonly int $turnBudgetSeconds = Package::DEFAULT_CLAIM_TTL,
        /** @var (Closure(): int)|null Monotonic nanoseconds; null = hrtime(true). Tests inject a fake clock. */
        private readonly ?Closure $clock = null,
    ) {}

    public function run(string $turnUlid): Turn
    {
        if ($this->context === null || $this->tools === null) {
            throw new RuntimeException('ConversationContextProvider and ToolCatalog must be bound before running a turn');
        }

        // The job (and its claim_ttl timeout) started just before this.
        $deadlineNs = $this->now() + $this->turnBudgetSeconds * 1_000_000_000;
        $llm = $this->llm instanceof DeadlineAwareLlmClient ? $this->llm->withDeadline($deadlineNs) : $this->llm;

        if (! $this->claim->claim($turnUlid, $this->claimOwner)) {
            throw new RuntimeException("Failed to claim turn {$turnUlid}");
        }

        $this->progress->append($turnUlid, ['kind' => 'status', 'data' => ['status' => Turn::STATUS_RUNNING]]);

        $usage = [];
        try {
            $turn = $this->store->turn($turnUlid);
            $conversation = $turn->conversation;
            if (! $conversation instanceof Conversation) {
                throw (new ModelNotFoundException)->setModel(Conversation::class, [$turn->conversation_id]);
            }
            $messages = $this->context->messagesForTurn($conversation->ulid, $turnUlid);
            // Do not advertise tools to clients that cannot continue after tool results.
            $toolDefs = $llm->supportsToolRounds()
                ? $this->tools->toolsForTurn($conversation->ulid, $turnUlid)
                : [];
            // Snapshot of what the model was shown; tool_calls outside it never reach the bus.
            $offeredNames = array_column($toolDefs, 'name');

            $rounds = 0;
            // 1-based tool-call count across all rounds of this turn → core D-013 agent turn budget.
            $toolCallCount = 0;
            $replied = false;
            while ($rounds < $this->maxToolRounds) {
                // Stop once the turn has left running: cancel, or a reaper that marked it failed.
                if (! $this->claim->isRunning($turnUlid)) {
                    return $this->stopped($turn, $usage);
                }
                if ($llm instanceof DeadlineAwareLlmClient
                    && $deadlineNs - $this->now() < DeadlineAwareLlmClient::MIN_REQUEST_SECONDS * 1_000_000_000) {
                    throw new RetryableLlmException(
                        "Turn time budget (claim_ttl {$this->turnBudgetSeconds}s) has under "
                        .DeadlineAwareLlmClient::MIN_REQUEST_SECONDS.'s left for another LLM round'
                    );
                }
                $rounds++;
                $startedAt = $this->now();
                $response = $llm->complete($messages, $toolDefs);
                $usage[] = $this->roundUsage($response, $startedAt);
                $toolCalls = $response['tool_calls'] ?? [];

                if ($toolCalls === []) {
                    $content = (string) ($response['content'] ?? '');
                    $this->store->createMessage($conversation, $this->ulid(), 'assistant', $content);
                    $this->maybeCreateProposalsFromFence($conversation, $turn, $content);
                    $replied = true;
                    break;
                }

                if ($this->bus === null) {
                    throw new RuntimeException('CapabilityBus required for tool calls');
                }

                // Fail closed before any bus mutation when the client cannot continue after tool results.
                if (! $llm->supportsToolRounds()) {
                    throw new RuntimeException(
                        'Bound LlmClient does not support multi-round tool results; refusing tool invokes (fail closed)'
                    );
                }

                // Principal once per tool-using round: conversation user as actor + caller=agent
                // (LLM-chosen tool calls are the agent surface, D-022), so the agent kill switch,
                // per-capability surface narrowing and agent approval rules apply.
                // Missing/invalid user_id fails closed (never ResolveActor::defaultUser).
                $actor = $this->actors->resolve($conversation->user_id);
                $invokeOptions = $this->actors->invokeOptions($actor, ResolveConversationActor::CALLER_AGENT);

                // Normalize ids first so assistant tool_use and role=tool share the same id.
                $normalizedCalls = [];
                foreach ($toolCalls as $callIndex => $call) {
                    if (! is_array($call)) {
                        continue;
                    }
                    $toolCallId = trim((string) ($call['id'] ?? ''));
                    if ($toolCallId === '') {
                        // Fail closed for correlation: clients must supply id; generate a round-local fallback.
                        $toolCallId = 'tool_call_'.$rounds.'_'.((int) $callIndex + 1);
                    }
                    $call['id'] = $toolCallId;
                    $normalizedCalls[] = $call;
                }

                if ($normalizedCalls === []) {
                    // tool_calls present but unusable — treat as text-only terminal content.
                    $content = (string) ($response['content'] ?? '');
                    $this->store->createMessage($conversation, $this->ulid(), 'assistant', $content);
                    $this->maybeCreateProposalsFromFence($conversation, $turn, $content);
                    $replied = true;
                    break;
                }

                // Providers (Anthropic tool_use / OpenAI tool_calls) need the assistant turn in the transcript.
                $messages[] = [
                    'role' => 'assistant',
                    'content' => (string) ($response['content'] ?? ''),
                    'tool_calls' => $normalizedCalls,
                ];

                foreach ($normalizedCalls as $call) {
                    // A cancel or reap that landed mid-round stops the next bus invoke.
                    if (! $this->claim->isRunning($turnUlid)) {
                        return $this->stopped($turn, $usage);
                    }
                    $name = (string) ($call['name'] ?? '');
                    $payload = $call['arguments'] ?? $call['input'] ?? [];
                    if (! is_array($payload)) {
                        $payload = [];
                    }
                    $toolCallId = (string) $call['id'];
                    // D-005: optional tool arg idempotency_key is transport, not capability input.
                    $idempotencyKey = $payload['idempotency_key'] ?? null;
                    unset($payload['idempotency_key']);
                    if (in_array($name, $offeredNames, true)) {
                        $callOptions = array_merge($invokeOptions, [
                            'agent_turn_tool_calls' => ++$toolCallCount,
                        ]);
                        if (is_scalar($idempotencyKey) && (string) $idempotencyKey !== '') {
                            $callOptions['idempotency_key'] = (string) $idempotencyKey;
                        }
                        $result = $this->bus->invoke($name, $payload, $callOptions);
                    } else {
                        $result = CapabilityResult::failure(
                            'capability_not_in_profile',
                            "Tool {$name} was not offered for this turn",
                        );
                    }
                    $toolContent = $this->encodeToolResult($name, $result);
                    $this->progress->append($turnUlid, [
                        'kind' => 'tool',
                        'data' => [
                            'name' => $name,
                            // Served live over turn-events HTTP — never echo secrets (D-010).
                            'payload' => Redactor::redact($payload),
                            'ok' => $result->ok,
                            'error_code' => $result->errorCode(),
                            'tool_call_id' => $toolCallId,
                        ],
                    ]);
                    $messages[] = [
                        'role' => 'tool',
                        'content' => $toolContent,
                        'tool_call_id' => $toolCallId,
                        'id' => $toolCallId,
                    ];
                }
            }

            // D-013 loop protection: every round asked for tools and none replied — fail loudly,
            // never a silent `completed` with no assistant message.
            if (! $replied) {
                throw new RuntimeException("max_tool_rounds ({$this->maxToolRounds}) reached without a final reply");
            }

            // CAS running→completed: a cancel (or reap) that landed first wins; no completed terminal.
            if (! $this->claim->complete($turnUlid, $usage)) {
                return $this->stopped($turn, $usage);
            }

            // Terminal progress AFTER DB completed
            $this->progress->append($turnUlid, [
                'kind' => 'terminal',
                'data' => ['status' => Turn::STATUS_COMPLETED],
            ]);

            return $this->store->turn($turnUlid);
        } catch (\Throwable $e) {
            // CAS running→failed: a turn cancelled (or reaped) mid-run keeps its status and events.
            if (! $this->claim->fail($turnUlid, $e->getMessage(), $usage)) {
                throw $e;
            }
            // retryable=true: transient LLM failure; the turn stays failed, but a caller may try again later.
            $error = ['message' => $e->getMessage(), 'retryable' => $e instanceof RetryableLlmException];
            if ($e instanceof RetryableLlmException && $e->retryAfterSeconds !== null) {
                $error['retry_after_seconds'] = $e->retryAfterSeconds;
            }
            $this->progress->append($turnUlid, ['kind' => 'error', 'data' => $error]);
            $this->progress->append($turnUlid, [
                'kind' => 'terminal',
                'data' => ['status' => Turn::STATUS_FAILED],
            ]);
            throw $e;
        }
    }

    /**
     * The turn left running under the runner (cancelled, reaped): keep its status and
     * terminal event, record the rounds' usage, and return the fresh row.
     *
     * @param  list<array<string, int>>  $usage
     */
    private function stopped(Turn $turn, array $usage): Turn
    {
        $this->claim->recordUsage($turn->ulid, $usage);

        return $this->store->turn($turn->ulid);
    }

    /**
     * One round's accounting: runner-measured latency plus any non-negative int
     * token counts the client reported (junk values are dropped, not coerced).
     *
     * @param  array<string, mixed>  $response
     * @return array{latency_ms: int, input_tokens?: int, output_tokens?: int}
     */
    private function roundUsage(array $response, int $startedAt): array
    {
        $round = ['latency_ms' => intdiv($this->now() - $startedAt, 1_000_000)];
        $reported = is_array($response['usage'] ?? null) ? $response['usage'] : [];
        foreach (['input_tokens', 'output_tokens'] as $key) {
            if (is_int($reported[$key] ?? null) && $reported[$key] >= 0) {
                $round[$key] = $reported[$key];
            }
        }

        return $round;
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : hrtime(true);
    }

    private function encodeToolResult(string $name, CapabilityResult $result): string
    {
        $wire = $result->toArray();
        $wire['name'] = $name;

        return json_encode($wire, JSON_THROW_ON_ERROR);
    }

    private function maybeCreateProposalsFromFence(Conversation $conversation, Turn $turn, string $content): void
    {
        if (! $this->proposalsEnabled) {
            return;
        }

        $fence = $this->proposalExtractor->parse($content);
        if ($fence->isInvalid()) {
            // Surface provider/prompt format drift instead of silently dropping the proposal.
            $this->progress->append($turn->ulid, ['kind' => 'proposal_invalid', 'data' => null]);

            return;
        }

        $data = $fence->data;
        if ($data === null) {
            return;
        }

        $target = isset($data['target_capability']) ? (string) $data['target_capability'] : null;

        $this->store->createProposal(
            $turn,
            $this->ulid(),
            (string) ($data['type'] ?? 'action'),
            $data['payload'] ?? $data,
            $target,
            $target === null ? null : $this->targetSchemaHash($target, $conversation->ulid, $turn->ulid),
        );
    }

    private function ulid(): string
    {
        return strtoupper(bin2hex(random_bytes(13)));
    }

    /**
     * Stamp the target's then-current input schema so accept can tell drift from a bad payload.
     */
    private function targetSchemaHash(string $target, string $conversationUlid, string $turnUlid): ?string
    {
        foreach ($this->tools?->toolsForTurn($conversationUlid, $turnUlid) ?? [] as $tool) {
            if (($tool['name'] ?? null) === $target) {
                return ToolSchemaHash::of($tool);
            }
        }

        return null;
    }
}
