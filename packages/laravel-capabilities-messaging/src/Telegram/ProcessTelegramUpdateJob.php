<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

/**
 * Laravel-bus-friendly job wrapper for ProcessTelegramUpdate (L-004).
 *
 * Serialisable payload only — domain work stays in ProcessTelegramUpdate.
 * Dispatched by LaravelUpdateQueue production wiring; unit tests call handle() directly.
 */
final class ProcessTelegramUpdateJob
{
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
