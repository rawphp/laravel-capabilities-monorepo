<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Jobs\RunTurnJob;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Message;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;

/**
 * Cheap message create — never calls LlmClient.
 */
final class ConversationService
{
    /**
     * @param  callable(object): mixed  $dispatch  Bus dispatch callable (never runs job inline in tests)
     */
    public function __construct(
        private readonly mixed $dispatch,
        private readonly ?ProgressStore $progress = null,
    ) {
        if (! is_callable($this->dispatch)) {
            throw new \InvalidArgumentException('dispatch must be callable');
        }
    }

    /**
     * @return array{conversation_ulid: string, message_ulid: string, turn_ulid: string}
     */
    public function createUserMessage(
        string $content,
        ?string $conversationUlid = null,
        ?string $userId = null,
        ?string $appId = null,
    ): array {
        $conversation = $conversationUlid
            ? Conversation::query()->where('ulid', $conversationUlid)->firstOrFail()
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

        ($this->dispatch)(new RunTurnJob($turn->ulid));

        $progress = $this->progress ?? new ArrayProgressStore;
        $progress->append($turn->ulid, [
            'kind' => 'status',
            'data' => ['status' => Turn::STATUS_QUEUED],
        ]);

        return [
            'conversation_ulid' => $conversation->ulid,
            'message_ulid' => $message->ulid,
            'turn_ulid' => $turn->ulid,
        ];
    }

    private function ulid(): string
    {
        return strtoupper(bin2hex(random_bytes(13)));
    }
}
