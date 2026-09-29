<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\CapabilitiesAi\Contracts\ConversationStore;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Jobs\RunTurnJob;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Message;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Package;
use Rawphp\CapabilitiesAi\Support\EloquentConversationStore;

/**
 * Cheap message create — never calls LlmClient.
 */
final class ConversationService
{
    public const DEFAULT_TURNS_PER_MINUTE = 20;

    /**
     * @param  callable(object): mixed  $dispatch  Bus dispatch callable (never runs job inline in tests)
     * @param  int  $maxConcurrentTurns  Ceiling on queued + running turns across all conversations; 0 = unlimited
     * @param  RateLimiter|null  $turnLimiter  Core D-013 limiter; null = no per-user limit
     * @param  int  $turnsPerMinute  Accepted messages (turns) per user per minute; 0 = unlimited
     * @param  ConversationStore  $store  Row persistence (Eloquent in production, in-memory in unit tests)
     */
    public function __construct(
        private readonly mixed $dispatch,
        private readonly ProgressStore $progress,
        private readonly int $claimTtl = Package::DEFAULT_CLAIM_TTL,
        private readonly bool $proposalsEnabled = true,
        private readonly int $maxConcurrentTurns = 0,
        private readonly ?RateLimiter $turnLimiter = null,
        private readonly int $turnsPerMinute = 0,
        private readonly ConversationStore $store = new EloquentConversationStore,
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
        if ($this->turnsPerMinute < 0) {
            throw new \InvalidArgumentException('turnsPerMinute must be zero (unlimited) or positive');
        }
    }

    /**
     * @return array{conversation_ulid: string, message_ulid: string, turn_ulid: string}
     *
     * @throws TurnRateLimitedException when $userId is over turns_per_minute (nothing persisted)
     * @throws TurnCapacityExceededException when queued + running turns are at the ceiling (nothing persisted)
     * @throws ConversationClosedException when appending to a closed conversation (nothing persisted)
     */
    public function createUserMessage(
        string $content,
        ?string $conversationUlid = null,
        ?string $userId = null,
        ?string $appId = null,
    ): array {
        $this->assertTurnRate($userId);
        $this->assertTurnCapacity();

        // A given $userId must own an existing conversation: the owner is the turn's bus actor.
        $conversation = $conversationUlid
            ? $this->store->ownedConversation($conversationUlid, $userId)
            : $this->store->createConversation($this->ulid(), $appId, $userId);

        if ($conversation->status === 'closed') {
            throw new ConversationClosedException($conversation->ulid);
        }

        $message = $this->store->createMessage($conversation, $this->ulid(), 'user', $content);
        $turn = $this->store->createQueuedTurn($conversation, $this->ulid());

        // Queued first: a sync driver or fast worker appends running/terminal inside dispatch.
        $this->progress->append($turn->ulid, [
            'kind' => 'status',
            'data' => ['status' => Turn::STATUS_QUEUED],
        ]);

        $job = new RunTurnJob($turn->ulid);
        $job->timeout = $this->claimTtl;
        ($this->dispatch)($job);

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

        $messages = array_map(static fn (Message $m): array => [
            'ulid' => $m->ulid,
            'role' => (string) $m->role,
            'content' => $m->content,
            'created_at' => $m->created_at?->toIso8601String(),
        ], $this->store->messages($conversation));

        $payload = [
            'conversation_ulid' => $conversation->ulid,
            'messages' => $messages,
            'proposals' => [],
        ];

        if ($this->proposalsEnabled) {
            $payload['proposals'] = array_map(static fn (Proposal $p): array => [
                'ulid' => $p->ulid,
                'status' => (string) $p->status,
                'type' => (string) $p->type,
                'target_capability' => $p->target_capability,
            ], $this->store->proposals($conversation));
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

        if ($this->store->hasActiveTurns($conversation)) {
            throw new \RuntimeException("Conversation {$conversationUlid} has queued or running turns");
        }

        if ($conversation->status !== 'closed') {
            $this->store->close($conversation);
        }

        return [
            'conversation_ulid' => $conversation->ulid,
            'status' => 'closed',
            'closed' => true,
        ];
    }

    /**
     * D-013 per-user turn budget, checked before any query so a flooding user costs nothing.
     * Server-side creates without a user are not limited here.
     */
    private function assertTurnRate(?string $userId): void
    {
        if ($this->turnLimiter === null || $this->turnsPerMinute === 0 || $userId === null) {
            return;
        }

        $key = 'rl:ai:user:'.$userId;
        if ($this->turnLimiter->tooManyAttempts($key, $this->turnsPerMinute)) {
            throw new TurnRateLimitedException($this->turnsPerMinute);
        }
        $this->turnLimiter->hit($key, 60);
    }

    /**
     * Soft ceiling: count-then-insert is not atomic, so concurrent creates may overshoot slightly.
     */
    private function assertTurnCapacity(): void
    {
        if ($this->maxConcurrentTurns === 0) {
            return;
        }

        if ($this->store->activeTurnCount() >= $this->maxConcurrentTurns) {
            throw new TurnCapacityExceededException($this->maxConcurrentTurns);
        }
    }

    private function owned(string $conversationUlid, string $ownerId): Conversation
    {
        return $this->store->ownedConversation($conversationUlid, $ownerId);
    }

    private function ulid(): string
    {
        return strtoupper(bin2hex(random_bytes(13)));
    }
}
