<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Rate-limit attempts keyed by actor + capability + surface (D-013 / L-008).
 *
 * Production multi-worker hosts use {@see \Rawphp\Capabilities\Support\LaravelCacheRateLimiter}
 * over a shared {@see RateLimitCache} (Illuminate Cache / Redis). Unit tests inject
 * {@see \Rawphp\Capabilities\Support\InMemoryRateLimiter} or LaravelCacheRateLimiter
 * with {@see \Rawphp\Capabilities\Support\ArrayRateLimitCache} — never live Redis.
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
