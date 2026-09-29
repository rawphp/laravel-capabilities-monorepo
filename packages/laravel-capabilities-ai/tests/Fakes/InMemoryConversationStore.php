<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Tests\Fakes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Rawphp\CapabilitiesAi\Contracts\ConversationStore;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Message;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\Turn;

/**
 * In-memory ConversationStore: unsaved model instances in arrays, no database.
 *
 * Rows are held by reference, so tests move a turn along with `turn($ulid)->status = …`.
 */
final class InMemoryConversationStore implements ConversationStore
{
    /** @var list<Conversation> */
    public array $conversations = [];

    /** @var list<Message> */
    public array $messages = [];

    /** @var list<Turn> */
    public array $turns = [];

    /** @var list<Proposal> */
    public array $proposals = [];

    private int $nextId = 1;

    private int $clock = 0;

    public function activeTurnCount(): int
    {
        return count(array_filter($this->turns, self::isActive(...)));
    }

    public function ownedConversation(string $conversationUlid, ?string $ownerId): Conversation
    {
        foreach ($this->conversations as $conversation) {
            if ($conversation->ulid === $conversationUlid && $conversation->user_id === $ownerId) {
                return $conversation;
            }
        }

        throw (new ModelNotFoundException)->setModel(Conversation::class, [$conversationUlid]);
    }

    public function createConversation(string $ulid, ?string $appId, ?string $userId): Conversation
    {
        return $this->conversations[] = $this->row(new Conversation, [
            'ulid' => $ulid,
            'app_id' => $appId,
            'user_id' => $userId,
            'status' => 'open',
        ]);
    }

    public function createMessage(Conversation $conversation, string $ulid, string $role, string $content): Message
    {
        return $this->messages[] = $this->row(new Message, [
            'conversation_id' => $conversation->id,
            'ulid' => $ulid,
            'role' => $role,
            'content' => $content,
        ]);
    }

    public function createQueuedTurn(Conversation $conversation, string $ulid): Turn
    {
        return $this->turns[] = $this->row(new Turn, [
            'conversation_id' => $conversation->id,
            'ulid' => $ulid,
            'status' => Turn::STATUS_QUEUED,
        ]);
    }

    public function messages(Conversation $conversation): array
    {
        return array_values(array_filter(
            $this->messages,
            static fn (Message $m): bool => $m->conversation_id === $conversation->id,
        ));
    }

    public function proposals(Conversation $conversation): array
    {
        return array_values(array_filter(
            $this->proposals,
            static fn (Proposal $p): bool => $p->conversation_id === $conversation->id,
        ));
    }

    public function hasActiveTurns(Conversation $conversation): bool
    {
        foreach ($this->turns as $turn) {
            if ($turn->conversation_id === $conversation->id && self::isActive($turn)) {
                return true;
            }
        }

        return false;
    }

    public function close(Conversation $conversation): void
    {
        $conversation->status = 'closed';
    }

    public function turn(string $ulid): Turn
    {
        foreach ($this->turns as $turn) {
            if ($turn->ulid === $ulid) {
                return $turn;
            }
        }

        throw (new ModelNotFoundException)->setModel(Turn::class, [$ulid]);
    }

    public function conversation(string $ulid): Conversation
    {
        foreach ($this->conversations as $conversation) {
            if ($conversation->ulid === $ulid) {
                return $conversation;
            }
        }

        throw (new ModelNotFoundException)->setModel(Conversation::class, [$ulid]);
    }

    /**
     * Seed a proposal row for history tests (proposals are created by TurnRunner, not this port).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function addProposal(Conversation $conversation, array $attributes): Proposal
    {
        return $this->proposals[] = $this->row(new Proposal, $attributes + ['conversation_id' => $conversation->id]);
    }

    /**
     * @template T of Model
     *
     * @param  T  $model
     * @param  array<string, mixed>  $attributes
     * @return T
     */
    private function row(Model $model, array $attributes): Model
    {
        // A fixed format keeps timestamp casts off the (absent) connection grammar.
        $model->setDateFormat('Y-m-d H:i:s');
        $at = sprintf('2026-01-01 00:00:%02d', $this->clock++ % 60);

        return $model->forceFill($attributes + [
            'id' => $this->nextId++,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private static function isActive(Turn $turn): bool
    {
        return in_array($turn->status, [Turn::STATUS_QUEUED, Turn::STATUS_RUNNING], true);
    }
}
