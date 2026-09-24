<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Illuminate\Support\Carbon;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesAi\Contracts\ConversationContextProvider;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Message;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ProposalFenceExtractor;
use Rawphp\CapabilitiesAi\Support\ResolveConversationActor;
use Rawphp\CapabilitiesAi\Support\RetryableLlmException;
use Rawphp\CapabilitiesAi\Support\ToolSchemaHash;
use RuntimeException;

/**
 * Run a claimed turn: LLM loop + bus-only tool invokes.
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
    ) {}

    public function run(string $turnUlid): Turn
    {
        if ($this->context === null || $this->tools === null) {
            throw new RuntimeException('ConversationContextProvider and ToolCatalog must be bound before running a turn');
        }

        $turn = $this->claim->claim($turnUlid, $this->claimOwner);
        if ($turn === null) {
            throw new RuntimeException("Failed to claim turn {$turnUlid}");
        }

        $this->progress->append($turnUlid, ['kind' => 'status', 'data' => ['status' => Turn::STATUS_RUNNING]]);

        try {
            $conversation = Conversation::query()->findOrFail($turn->conversation_id);
            $messages = $this->context->messagesForTurn($conversation->ulid, $turnUlid);
            // Do not advertise tools to clients that cannot continue after tool results.
            $toolDefs = $this->llm->supportsToolRounds()
                ? $this->tools->toolsForTurn($conversation->ulid, $turnUlid)
                : [];
            // Snapshot of what the model was shown; tool_calls outside it never reach the bus.
            $offeredNames = array_column($toolDefs, 'name');

            $rounds = 0;
            $usage = [];
            // 1-based tool-call count across all rounds of this turn → core D-013 agent turn budget.
            $toolCallCount = 0;
            while ($rounds < $this->maxToolRounds) {
                $rounds++;
                $startedAt = hrtime(true);
                $response = $this->llm->complete($messages, $toolDefs);
                $usage[] = $this->roundUsage($response, $startedAt);
                $toolCalls = $response['tool_calls'] ?? [];

                if ($toolCalls === []) {
                    $content = (string) ($response['content'] ?? '');
                    Message::query()->create([
                        'conversation_id' => $conversation->id,
                        'ulid' => strtoupper(bin2hex(random_bytes(13))),
                        'role' => 'assistant',
                        'content' => $content,
                        'meta' => null,
                    ]);
                    $this->maybeCreateProposalsFromFence($conversation, $turn, $content);
                    break;
                }

                if ($this->bus === null) {
                    throw new RuntimeException('CapabilityBus required for tool calls');
                }

                // Fail closed before any bus mutation when the client cannot continue after tool results.
                if (! $this->llm->supportsToolRounds()) {
                    throw new RuntimeException(
                        'Bound LlmClient does not support multi-round tool results; refusing tool invokes (fail closed)'
                    );
                }

                // Principal once per tool-using round: conversation user as actor + caller=job.
                // Missing/invalid user_id fails closed (never ResolveActor::defaultUser).
                $actor = $this->actors->resolve($conversation->user_id);
                $invokeOptions = $this->actors->invokeOptions($actor);

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
                    Message::query()->create([
                        'conversation_id' => $conversation->id,
                        'ulid' => strtoupper(bin2hex(random_bytes(13))),
                        'role' => 'assistant',
                        'content' => $content,
                        'meta' => null,
                    ]);
                    $this->maybeCreateProposalsFromFence($conversation, $turn, $content);
                    break;
                }

                // Providers (Anthropic tool_use / OpenAI tool_calls) need the assistant turn in the transcript.
                $messages[] = [
                    'role' => 'assistant',
                    'content' => (string) ($response['content'] ?? ''),
                    'tool_calls' => $normalizedCalls,
                ];

                foreach ($normalizedCalls as $call) {
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
                            'payload' => $payload,
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

            // Cooperative cancel: do not overwrite cancelled mid-run
            $fresh = Turn::query()->where('ulid', $turnUlid)->first();
            if ($fresh !== null && $fresh->status === Turn::STATUS_CANCELLED) {
                $fresh->usage = $usage;
                $fresh->save();

                return $fresh;
            }

            $turn->status = Turn::STATUS_COMPLETED;
            $turn->usage = $usage;
            $turn->finished_at = Carbon::now();
            $turn->save();

            // Terminal progress AFTER DB completed
            $this->progress->append($turnUlid, [
                'kind' => 'terminal',
                'data' => ['status' => Turn::STATUS_COMPLETED],
            ]);

            return $turn->refresh();
        } catch (\Throwable $e) {
            $fresh = Turn::query()->where('ulid', $turnUlid)->first();
            if ($fresh !== null && $fresh->status === Turn::STATUS_CANCELLED) {
                // Cancelled mid-run — do not overwrite with failed / terminal failed
                throw $e;
            }

            $turn->status = Turn::STATUS_FAILED;
            $turn->error = $e->getMessage();
            $turn->usage = $usage ?? null;
            $turn->finished_at = Carbon::now();
            $turn->save();
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
     * One round's accounting: runner-measured latency plus any non-negative int
     * token counts the client reported (junk values are dropped, not coerced).
     *
     * @param  array<string, mixed>  $response
     * @return array{latency_ms: int, input_tokens?: int, output_tokens?: int}
     */
    private function roundUsage(array $response, int $startedAt): array
    {
        $round = ['latency_ms' => intdiv(hrtime(true) - $startedAt, 1_000_000)];
        $reported = is_array($response['usage'] ?? null) ? $response['usage'] : [];
        foreach (['input_tokens', 'output_tokens'] as $key) {
            if (is_int($reported[$key] ?? null) && $reported[$key] >= 0) {
                $round[$key] = $reported[$key];
            }
        }

        return $round;
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

        Proposal::query()->create([
            'turn_id' => $turn->id,
            'conversation_id' => $conversation->id,
            'ulid' => strtoupper(bin2hex(random_bytes(13))),
            'type' => (string) ($data['type'] ?? 'action'),
            'payload' => $data['payload'] ?? $data,
            'target_capability' => $target,
            'schema_hash' => $target === null ? null : $this->targetSchemaHash($target, $conversation->ulid, $turn->ulid),
            'status' => Proposal::STATUS_PENDING,
        ]);
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
