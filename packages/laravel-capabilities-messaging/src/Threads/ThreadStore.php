<?php

namespace Rawphp\CapabilitiesMessaging\Threads;

use RuntimeException;

/**
 * Maps chat + topic to a conversation thread id.
 *
 * The update pipeline calls only {@see threadIdFor()} and stores nothing, so a long-lived queue
 * worker does not grow with traffic. The in-memory thread/history methods are a process-local
 * helper that the pipeline does not write; conversation memory belongs to the host AgentTurn.
 * Topics are isolated: chat A topic 1 cannot read topic 2 history.
 * Not final so hosts (and tests) can swap the storage behind the same API.
 */
class ThreadStore
{
    /** @var array<string, array{id: string, chat_id: string, topic_id: string|null, history: list<array<string, mixed>>}> */
    private array $threads = [];

    /**
     * Stable thread id for chat + optional topic.
     */
    public function threadIdFor(string $chatId, string|int|null $topicId = null): string
    {
        $topic = $topicId === null || $topicId === '' ? 'null' : (string) $topicId;

        return 'tg:'.$chatId.':'.$topic;
    }

    /**
     * @return array{id: string, chat_id: string, topic_id: string|null, history: list<array<string, mixed>>}
     */
    public function getOrCreate(string $chatId, string|int|null $topicId = null): array
    {
        $id = $this->threadIdFor($chatId, $topicId);
        if (! isset($this->threads[$id])) {
            $this->threads[$id] = [
                'id' => $id,
                'chat_id' => $chatId,
                'topic_id' => $topicId === null || $topicId === '' ? null : (string) $topicId,
                'history' => [],
            ];
        }

        return $this->threads[$id];
    }

    /**
     * @return array{id: string, chat_id: string, topic_id: string|null, history: list<array<string, mixed>>}|null
     */
    public function find(string $chatId, string|int|null $topicId = null): ?array
    {
        $id = $this->threadIdFor($chatId, $topicId);

        return $this->threads[$id] ?? null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function appendHistory(string $threadId, array $message): void
    {
        if (! isset($this->threads[$threadId])) {
            throw new RuntimeException(sprintf('Unknown thread "%s".', $threadId));
        }

        $this->threads[$threadId]['history'][] = $message;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(string $threadId): array
    {
        return $this->threads[$threadId]['history'] ?? [];
    }

    /**
     * Unknown chat without create — must not leak other threads' history.
     *
     * @return list<array<string, mixed>>
     */
    public function historyForChat(string $chatId, string|int|null $topicId = null, bool $create = false): array
    {
        if ($create) {
            $thread = $this->getOrCreate($chatId, $topicId);

            return $thread['history'];
        }

        $thread = $this->find($chatId, $topicId);

        return $thread['history'] ?? [];
    }
}
