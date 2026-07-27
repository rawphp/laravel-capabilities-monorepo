<?php

namespace Rawphp\Capabilities\Support;

use DateTimeImmutable;
use Rawphp\Capabilities\Contracts\Clock;

/**
 * Wall-clock implementation for production bindings.
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}
