<?php

namespace Rawphp\Capabilities\Contracts;

use DateTimeImmutable;

/**
 * Injectable clock for TTL, expiry, and lease logic.
 *
 * Unit tests use {@see \Rawphp\Capabilities\Support\FixedClock}; production
 * uses {@see \Rawphp\Capabilities\Support\SystemClock}.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
