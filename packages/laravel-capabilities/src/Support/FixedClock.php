<?php

namespace Rawphp\Capabilities\Support;

use DateInterval;
use DateTimeImmutable;
use Rawphp\Capabilities\Contracts\Clock;

/**
 * Deterministic clock for unit tests and expiry scenarios.
 */
final class FixedClock implements Clock
{
    public function __construct(
        private DateTimeImmutable $now,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    public function advance(DateInterval $interval): void
    {
        $this->now = $this->now->add($interval);
    }
}
