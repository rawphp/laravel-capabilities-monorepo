<?php

namespace Rawphp\Capabilities\Pipeline;

use Rawphp\Capabilities\Registry\CapabilityRegistry;

/**
 * Mutable observation bag for a registry / pipeline instance.
 *
 * Holds last-invoke traces and in-memory events used by unit tests and
 * {@see CapabilityRegistry} accessors.
 */
final class InvokeObservation
{
    /** @var list<object> */
    public array $failedEvents = [];

    /** @var list<object> */
    public array $invokedEvents = [];

    /** @var list<object> */
    public array $approvalEvents = [];

    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $logs = [];

    /** @var list<string> */
    public array $lastStages = [];

    public ?InvokeState $lastState = null;

    public ?string $lastRateLimitKey = null;

    public bool $lastRunWasWrapped = false;

    public ?float $invokeStartedAt = null;

    public function beginInvoke(): void
    {
        $this->lastStages = [];
        $this->invokeStartedAt = microtime(true);
        $this->lastRunWasWrapped = false;
        $this->lastRateLimitKey = null;
        $this->lastState = null;
    }
}
