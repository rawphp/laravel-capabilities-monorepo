<?php

namespace Rawphp\Capabilities\Contracts;

use Rawphp\Capabilities\Support\ArrayRateLimitCache;
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\Capabilities\Support\LaravelCacheRateLimiter;

/**
 * Rate-limit attempts keyed by actor + capability + surface (D-013 / L-008).
 *
 * Production multi-worker hosts use {@see LaravelCacheRateLimiter}
 * over a shared {@see RateLimitCache} (Illuminate Cache / Redis). Unit tests inject
 * {@see InMemoryRateLimiter} or LaravelCacheRateLimiter
 * with {@see ArrayRateLimitCache} — never live Redis.
 */
interface RateLimiter
{
    public function tooManyAttempts(string $key, int $maxAttempts): bool;

    /**
     * Record a hit and return the current attempt count within the decay window.
     */
    public function hit(string $key, int $decaySeconds = 60): int;

    public function remaining(string $key, int $maxAttempts): int;

    /**
     * Seconds until the key's current decay window frees; 0 when the key holds no window.
     * Feeds `error.retry_after` / `Retry-After` on rate_limited (C-007).
     */
    public function availableIn(string $key): int;

    public function clear(string $key): void;
}
