<?php

namespace Rawphp\Capabilities\Pipeline;

use Rawphp\Capabilities\Registry\CapabilityRegistry;

/**
 * Mutable observation bag for a registry / pipeline instance.
 *
 * Holds last-invoke traces and in-memory events used by unit tests and
 * {@see CapabilityRegistry} accessors. The registry is a long-lived singleton (queue
 * workers, Octane), so the event/log lists are capped at {@see MAX_RETAINED} newest
 * entries — host listeners get every event through the dispatcher (L-007), this is a
 * diagnostic window, not the delivery channel.
 */
final class InvokeObservation
{
    public const MAX_RETAINED = 100;

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

    public function recordInvoked(object $event): void
    {
        $this->retain($this->invokedEvents, $event);
    }

    public function recordFailed(object $event): void
    {
        $this->retain($this->failedEvents, $event);
    }

    public function recordApproval(object $event): void
    {
        $this->retain($this->approvalEvents, $event);
    }

    /**
     * @param  array{level: string, message: string, context: array<string, mixed>}  $log
     */
    public function log(array $log): void
    {
        $this->retain($this->logs, $log);
    }

    /**
     * @template T
     *
     * @param  list<T>  $list
     * @param  T  $item
     */
    private function retain(array &$list, mixed $item): void
    {
        $list[] = $item;
        if (count($list) > self::MAX_RETAINED) {
            $list = array_slice($list, -self::MAX_RETAINED);
        }
    }

    public function beginInvoke(): void
    {
        $this->lastStages = [];
        $this->invokeStartedAt = microtime(true);
        $this->lastRunWasWrapped = false;
        $this->lastRateLimitKey = null;
        $this->lastState = null;
    }
}
