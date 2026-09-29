<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Jobs\RunTurnJob;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Message;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Package;

/**
 * Cheap message create — never calls LlmClient.
 */
final class ConversationService
{
    /**
     * @param  callable(object): mixed  $dispatch  Bus dispatch callable (never runs job inline in tests)
     * @param  int  $maxConcurrentTurns  Ceiling on queued + running turns across all conversations; 0 = unlimited
     */
    public function __construct(
        private readonly mixed $dispatch,
        private readonly ProgressStore $progress,
        private readonly int $claimTtl = Package::DEFAULT_CLAIM_TTL,
        private readonly bool $proposalsEnabled = true,
        private readonly int $maxConcurrentTurns = 0,
    ) {
        if (! is_callable($this->dispatch)) {
            throw new \InvalidArgumentException('dispatch must be callable');
        }
        if ($this->claimTtl <= 0) {
            throw new \InvalidArgumentException('claimTtl must be positive');
        }
        if ($this->maxConcurrentTurns < 0) {
            throw new \InvalidArgumentException('maxConcurrentTurns must be zero (unlimited) or positive');
        }
    }

    /**
     * @return array{conversation_ulid: string, message_ulid: string, turn_ulid: string}
     *
     * @throws TurnCapacityExceededException when queued + running turns are at the ceiling (nothing persisted)
     */
    public function createUserMessage(
        string $content,
        ?string $conversationUlid = null,
        ?string $userId = null,
        ?string $appId = null,
    ): array {
        $this->assertTurnCapacity();

        // A given $userId must own an existing conversation: the owner is the turn's bus actor.
        $conversation = $conversationUlid
            ? Conversation::query()->where('ulid', $conversationUlid)->where('user_id', $userId)->firstOrFail()
            : Conversation::query()->create([
                'ulid' => $this->ulid(),
                'app_id' => $appId,
                'user_id' => $userId,
                'status' => 'open',
                'meta' => null,
            ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'ulid' => $this->ulid(),
            'role' => 'user',
            'content' => $content,
            'meta' => null,
        ]);

        $turn = Turn::query()->create([
            'conversation_id' => $conversation->id,
            'ulid' => $this->ulid(),
            'status' => Turn::STATUS_QUEUED,
            'idempotency_key' => null,
            'request_hash' => null,
        ]);

        $job = new RunTurnJob($turn->ulid);
        $job->timeout = $this->claimTtl;
        ($this->dispatch)($job);

        $this->progress->append($turn->ulid, [
            'kind' => 'status',
            'data' => ['status' => Turn::STATUS_QUEUED],
        ]);

        return [
            'conversation_ulid' => $conversation->ulid,
            'message_ulid' => $message->ulid,
            'turn_ulid' => $turn->ulid,
        ];
    }

    /**
     * Ordered messages for a conversation (HTTP history). Another owner's conversation is not found.
     *
     * @return array{
     *     conversation_ulid: string,
     *     messages: list<array{ulid: string, role: string, content: ?string, created_at: ?string}>,
     *     proposals: list<array{ulid: string, status: string, type: string, target_capability: ?string}>
     * }
     */
    public function history(string $conversationUlid, string $ownerId): array
    {
        $conversation = $this->owned($conversationUlid, $ownerId);

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(static fn (Message $m): array => [
                'ulid' => $m->ulid,
                'role' => (string) $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->all();

        $payload = [
            'conversation_ulid' => $conversation->ulid,
            'messages' => $messages,
            'proposals' => [],
        ];

        if ($this->proposalsEnabled) {
            $payload['proposals'] = Proposal::query()
                ->where('conversation_id', $conversation->id)
                ->orderBy('id')
                ->get()
                ->map(static fn (Proposal $p): array => [
                    'ulid' => $p->ulid,
                    'status' => (string) $p->status,
                    'type' => (string) $p->type,
                    'target_capability' => $p->target_capability,
                ])
                ->all();
        }

        return $payload;
    }

    /**
     * Close conversation (status=closed). Fail closed if any turn is queued or running.
     * Idempotent when already closed and no active turns. Another owner's conversation is not found.
     *
     * @return array{conversation_ulid: string, status: string, closed: bool}
     */
    public function destroy(string $conversationUlid, string $ownerId): array
    {
        $conversation = $this->owned($conversationUlid, $ownerId);

        $active = Turn::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', [Turn::STATUS_QUEUED, Turn::STATUS_RUNNING])
            ->exists();

        if ($active) {
            throw new \RuntimeException("Conversation {$conversationUlid} has queued or running turns");
        }

        if ($conversation->status !== 'closed') {
            $conversation->status = 'closed';
            $conversation->save();
        }

        return [
            'conversation_ulid' => $conversation->ulid,
            'status' => 'closed',
            'closed' => true,
        ];
    }

    /**
     * Soft ceiling: count-then-insert is not atomic, so concurrent creates may overshoot slightly.
     */
    private function assertTurnCapacity(): void
    {
        if ($this->maxConcurrentTurns === 0) {
            return;
        }

        $active = Turn::query()
            ->whereIn('status', [Turn::STATUS_QUEUED, Turn::STATUS_RUNNING])
            ->count();

        if ($active >= $this->maxConcurrentTurns) {
            throw new TurnCapacityExceededException($this->maxConcurrentTurns);
        }
    }

    private function owned(string $conversationUlid, string $ownerId): Conversation
    {
        return Conversation::query()
            ->where('ulid', $conversationUlid)
            ->where('user_id', $ownerId)
            ->firstOrFail();
    }

    private function ulid(): string
    {
        return strtoupper(bin2hex(random_bytes(13)));
    }
}
