<?php

namespace Rawphp\CapabilitiesMessaging\Support;

use RuntimeException;

/**
 * In-memory queue dispatcher for unit tests (driver=fake / APP_ENV=testing only).
 * Production uses LaravelUpdateQueue (L-004) — do not default this outside tests.
 */
final class FakeQueue implements UpdateQueue
{
    /** @var list<array{job: string, payload: array<string, mixed>}> */
    private array $pushed = [];

    private bool $failNext = false;

    public function failNextPush(bool $fail = true): void
    {
        $this->failNext = $fail;
    }

    public function push(string $job, array $payload): void
    {
        if ($this->failNext) {
            $this->failNext = false;
            throw new RuntimeException('Queue push failed (fake).');
        }

        $this->pushed[] = ['job' => $job, 'payload' => $payload];
    }

    /**
     * @return list<array{job: string, payload: array<string, mixed>}>
     */
    public function pushed(): array
    {
        return $this->pushed;
    }

    public function count(): int
    {
        return count($this->pushed);
    }

    public function reset(): void
    {
        $this->pushed = [];
        $this->failNext = false;
    }
}
