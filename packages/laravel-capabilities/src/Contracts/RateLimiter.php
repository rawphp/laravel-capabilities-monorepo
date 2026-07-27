<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Rate-limit attempts keyed by actor + capability + surface (D-013).
 *
 * Production may wrap Laravel's RateLimiter; unit tests inject
 * {@see \Rawphp\Capabilities\Support\InMemoryRateLimiter}.
 */
interface RateLimiter
{
    public function tooManyAttempts(string $key, int $maxAttempts): bool;

    /**
     * Record a hit and return the current attempt count within the decay window.
     */
    public function hit(string $key, int $decaySeconds = 60): int;

    public function remaining(string $key, int $maxAttempts): int;

    public function clear(string $key): void;
}
