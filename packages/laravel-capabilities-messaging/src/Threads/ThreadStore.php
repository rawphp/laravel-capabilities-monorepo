<?php

namespace Rawphp\CapabilitiesMessaging\Threads;

use RuntimeException;

/**
 * Conversation thread persistence (in-memory for unit tests; swap driver in production).
 *
 * Topics are isolated: chat A topic 1 cannot read topic 2 history.
 */
final class ThreadStore
{
    /** @var array<string, array{id: string, chat_id: string, topic_id: string|null, history: list<array<string, mixed>>}> */
    private array $threads = [];

    private bool $failNext = false;

    public function failNext(bool $fail = true): void
    {
        $this->failNext = $fail;
    }

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
        $this->maybeFail();

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
        $this->maybeFail();
        $id = $this->threadIdFor($chatId, $topicId);

        return $this->threads[$id] ?? null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function appendHistory(string $threadId, array $message): void
    {
        $this->maybeFail();

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
        $this->maybeFail();

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

    private function maybeFail(): void
    {
        if ($this->failNext) {
            $this->failNext = false;
            throw new RuntimeException('Thread store failure (fake).');
        }
    }
}
