<?php

namespace Rawphp\CapabilitiesMessaging\Support;

/**
 * Production UpdateQueue adapter — dispatches via an injected Laravel bus/job dispatcher.
 *
 * Unit tests inject a callable; MessagingServiceProvider wires
 * Illuminate\Contracts\Bus\Dispatcher + ProcessTelegramUpdateJob.
 * FakeQueue remains for driver=fake / testing only (L-004).
 */
final class LaravelUpdateQueue implements UpdateQueue
{
    /** @var callable(string $job, array<string, mixed> $payload): void */
    private $dispatcher;

    /**
     * @param  callable(string $job, array<string, mixed> $payload): void  $dispatcher
     */
    public function __construct(callable $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function push(string $job, array $payload): void
    {
        ($this->dispatcher)($job, $payload);
    }
}
