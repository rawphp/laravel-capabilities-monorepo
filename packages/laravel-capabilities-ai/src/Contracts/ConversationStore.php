<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Contracts;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Message;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\EloquentConversationStore;

/**
 * Row persistence for the package's conversation / message / turn / proposal tables.
 * Turn status transitions live on {@see TurnClaim}.
 *
 * Production: {@see EloquentConversationStore}.
 * Unit tests bind an in-memory fake, so conversation, turn and proposal rules run without a database.
 */
interface ConversationStore
{
    /** Queued + running turns across all conversations. */
    public function activeTurnCount(): int;

    /**
     * The conversation with this ulid owned by $ownerId (null matches ownerless rows only).
     *
     * @throws ModelNotFoundException when missing or owned by someone else
     */
    public function ownedConversation(string $conversationUlid, ?string $ownerId): Conversation;

    public function createConversation(string $ulid, ?string $appId, ?string $userId): Conversation;

    public function createMessage(Conversation $conversation, string $ulid, string $role, string $content): Message;

    public function createQueuedTurn(Conversation $conversation, string $ulid): Turn;

    /**
     * @return list<Message> oldest first (created_at, then id)
     */
    public function messages(Conversation $conversation): array;

    /**
     * @return list<Proposal> in creation order
     */
    public function proposals(Conversation $conversation): array;

    public function hasActiveTurns(Conversation $conversation): bool;

    public function close(Conversation $conversation): void;

    /**
     * The turn with this ulid, its conversation loaded.
     *
     * @throws ModelNotFoundException when missing
     */
    public function turn(string $turnUlid): Turn;

    /**
     * The turn with this ulid whose conversation is owned by $ownerId, its conversation loaded.
     *
     * @throws ModelNotFoundException when missing, ownerless, or owned by someone else
     */
    public function ownedTurn(string $turnUlid, string $ownerId): Turn;

    /**
     * A pending proposal on the turn's conversation.
     */
    public function createProposal(
        Turn $turn,
        string $ulid,
        string $type,
        mixed $payload,
        ?string $targetCapability,
        ?string $schemaHash,
    ): Proposal;

    /**
     * The proposal with this ulid, its conversation and turn loaded (null when those rows are gone).
     *
     * @throws ModelNotFoundException when missing
     */
    public function proposal(string $proposalUlid): Proposal;

    /**
     * True only when the proposal exists and its conversation is owned by $ownerId.
     */
    public function proposalOwnedBy(string $proposalUlid, string $ownerId): bool;

    /**
     * Atomic proposal status transition: UPDATE … WHERE ulid AND status = $fromStatus.
     *
     * @param  array<string, mixed>  $attributes  Columns to set (status included)
     * @return bool false when the proposal is missing or already left $fromStatus
     */
    public function transitionProposal(string $proposalUlid, string $fromStatus, array $attributes): bool;
}
