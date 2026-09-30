<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued Laravel job wrapper for ProcessTelegramUpdate (L-004).
 *
 * Serialisable payload only — domain work stays in ProcessTelegramUpdate.
 * Dispatched by LaravelUpdateQueue production wiring, so the webhook answers Telegram
 * without waiting on the agent turn. A {@see RetryableUpdateFailure} (transient reply send
 * failure) fails the job and the queue retries; terminal outcomes return normally.
 */
final class ProcessTelegramUpdateJob implements ShouldQueue
{
    /** Finite attempts; a retry only re-sends a reply that failed transiently (D-005). */
    public int $tries = 3;

    /**
     * Seconds before the worker kills an attempt: room for two agent calls, the tool invokes and
     * the reply. The queue connection's retry_after must be longer. A killed attempt's retry
     * ends without a second agent turn (M-205).
     */
    public int $timeout = 120;

    /** @var list<int> seconds between attempts */
    public array $backoff = [10, 60];

    /** Laravel bus / queue worker read these public props (no Queueable trait required). */
    public ?string $queue = null;

    public ?string $connection = null;

    /**
     * @param  array<string, mixed>  $update  Telegram Update payload
     */
    public function __construct(
        public readonly array $update,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(ProcessTelegramUpdate $processor): array
    {
        return $processor->handle($this->update);
    }
}
