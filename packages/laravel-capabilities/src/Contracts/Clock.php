<?php

namespace Rawphp\Capabilities\Contracts;

use DateTimeImmutable;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\SystemClock;

/**
 * Injectable clock for TTL, expiry, and lease logic.
 *
 * Unit tests use {@see FixedClock}; production
 * uses {@see SystemClock}.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
