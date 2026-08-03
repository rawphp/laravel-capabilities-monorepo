<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Illuminate\Support\Carbon;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\CapabilitiesAi\Contracts\ConversationContextProvider;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Message;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\Turn;
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
            $toolDefs = $this->tools->toolsForTurn($conversation->ulid, $turnUlid);

            $rounds = 0;
            while ($rounds < $this->maxToolRounds) {
                $rounds++;
                $response = $this->llm->complete($messages, $toolDefs);
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
                    $this->maybeCreateProposalsFromFence($conversation->id, $turn->id, $content);
                    break;
                }

                if ($this->bus === null) {
                    throw new RuntimeException('CapabilityBus required for tool calls');
                }

                foreach ($toolCalls as $call) {
                    $name = (string) ($call['name'] ?? '');
                    $payload = $call['arguments'] ?? $call['input'] ?? [];
                    if (! is_array($payload)) {
                        $payload = [];
                    }
                    $this->bus->invoke($name, $payload);
                    $this->progress->append($turnUlid, [
                        'kind' => 'tool',
                        'data' => ['name' => $name, 'payload' => $payload],
                    ]);
                    $messages[] = [
                        'role' => 'tool',
                        'content' => json_encode(['ok' => true, 'name' => $name], JSON_THROW_ON_ERROR),
                    ];
                }
            }

            // Cooperative cancel: do not overwrite cancelled mid-run
            $fresh = Turn::query()->where('ulid', $turnUlid)->first();
            if ($fresh !== null && $fresh->status === Turn::STATUS_CANCELLED) {
                return $fresh;
            }

            $turn->status = Turn::STATUS_COMPLETED;
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
            $turn->finished_at = Carbon::now();
            $turn->save();
            $this->progress->append($turnUlid, [
                'kind' => 'error',
                'data' => ['message' => $e->getMessage()],
            ]);
            $this->progress->append($turnUlid, [
                'kind' => 'terminal',
                'data' => ['status' => Turn::STATUS_FAILED],
            ]);
            throw $e;
        }
    }

    private function maybeCreateProposalsFromFence(int $conversationId, int $turnId, string $content): void
    {
        if (! preg_match('/```proposal\s*(\{.*?\})\s*```/s', $content, $m)) {
            return;
        }
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        Proposal::query()->create([
            'turn_id' => $turnId,
            'conversation_id' => $conversationId,
            'ulid' => strtoupper(bin2hex(random_bytes(13))),
            'type' => (string) ($data['type'] ?? 'action'),
            'payload' => $data['payload'] ?? $data,
            'target_capability' => isset($data['target_capability']) ? (string) $data['target_capability'] : null,
            'status' => Proposal::STATUS_PENDING,
        ]);
    }
}
