<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Rawphp\CapabilitiesAi\Contracts\ConversationStore;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Message;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\Turn;

/**
 * Eloquent-backed {@see ConversationStore} over the package's own capabilities_ai_* tables.
 */
final class EloquentConversationStore implements ConversationStore
{
    private const ACTIVE = [Turn::STATUS_QUEUED, Turn::STATUS_RUNNING];

    public function activeTurnCount(): int
    {
        return Turn::query()->whereIn('status', self::ACTIVE)->count();
    }

    public function ownedConversation(string $conversationUlid, ?string $ownerId): Conversation
    {
        return Conversation::query()
            ->where('ulid', $conversationUlid)
            ->where('user_id', $ownerId)
            ->firstOrFail();
    }

    public function createConversation(string $ulid, ?string $appId, ?string $userId): Conversation
    {
        return Conversation::query()->create([
            'ulid' => $ulid,
            'app_id' => $appId,
            'user_id' => $userId,
            'status' => 'open',
            'meta' => null,
        ]);
    }

    public function createMessage(Conversation $conversation, string $ulid, string $role, string $content): Message
    {
        return Message::query()->create([
            'conversation_id' => $conversation->id,
            'ulid' => $ulid,
            'role' => $role,
            'content' => $content,
            'meta' => null,
        ]);
    }

    public function createQueuedTurn(Conversation $conversation, string $ulid): Turn
    {
        return Turn::query()->create([
            'conversation_id' => $conversation->id,
            'ulid' => $ulid,
            'status' => Turn::STATUS_QUEUED,
            'idempotency_key' => null,
            'request_hash' => null,
        ]);
    }

    public function messages(Conversation $conversation): array
    {
        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function proposals(Conversation $conversation): array
    {
        return Proposal::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function hasActiveTurns(Conversation $conversation): bool
    {
        return Turn::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', self::ACTIVE)
            ->exists();
    }

    public function close(Conversation $conversation): void
    {
        $conversation->status = 'closed';
        $conversation->save();
    }

    public function turn(string $turnUlid): Turn
    {
        return Turn::query()->with('conversation')->where('ulid', $turnUlid)->firstOrFail();
    }

    public function ownedTurn(string $turnUlid, string $ownerId): Turn
    {
        return Turn::query()
            ->with('conversation')
            ->where('ulid', $turnUlid)
            ->whereHas('conversation', static fn (Builder $q) => $q->where('user_id', $ownerId))
            ->firstOrFail();
    }

    public function createProposal(
        Turn $turn,
        string $ulid,
        string $type,
        mixed $payload,
        ?string $targetCapability,
        ?string $schemaHash,
    ): Proposal {
        return Proposal::query()->create([
            'turn_id' => $turn->id,
            'conversation_id' => $turn->conversation_id,
            'ulid' => $ulid,
            'type' => $type,
            'payload' => $payload,
            'target_capability' => $targetCapability,
            'schema_hash' => $schemaHash,
            'status' => Proposal::STATUS_PENDING,
        ]);
    }

    public function proposal(string $proposalUlid): Proposal
    {
        return Proposal::query()->with(['conversation', 'turn'])->where('ulid', $proposalUlid)->firstOrFail();
    }

    public function proposalOwnedBy(string $proposalUlid, string $ownerId): bool
    {
        return Proposal::query()
            ->where('ulid', $proposalUlid)
            ->whereHas('conversation', static fn (Builder $q) => $q->where('user_id', $ownerId))
            ->exists();
    }

    public function transitionProposal(string $proposalUlid, string $fromStatus, array $attributes): bool
    {
        return Proposal::query()->toBase()
            ->where('ulid', $proposalUlid)
            ->where('status', $fromStatus)
            ->update($attributes + ['updated_at' => Carbon::now()->toDateTimeString()]) === 1;
    }
}
