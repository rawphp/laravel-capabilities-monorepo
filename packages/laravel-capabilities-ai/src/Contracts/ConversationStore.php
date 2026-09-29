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
 * Persistence port for ConversationService (conversation / message / queued-turn rows).
 *
 * Production: {@see EloquentConversationStore}.
 * Unit tests bind an in-memory fake, so conversation rules run without a database.
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
}
