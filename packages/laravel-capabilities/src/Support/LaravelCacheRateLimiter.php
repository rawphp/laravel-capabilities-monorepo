<?php

namespace Rawphp\Capabilities\Support;

use Rawphp\Capabilities\Contracts\RateLimitCache;
use Rawphp\Capabilities\Contracts\RateLimiter;

/**
 * Shared-store {@see RateLimiter} for multi-worker hosts (L-008 / D-013).
 *
 * Counters live in an injectable {@see RateLimitCache} (typically Illuminate
 * Cache / Redis). Unit tests inject {@see ArrayRateLimitCache} — no live Redis.
 *
 * Process-local {@see InMemoryRateLimiter} remains for single-worker unit tests
 * only; production multi-worker must use this adapter via rate_limits.driver=cache.
 */
final class LaravelCacheRateLimiter implements RateLimiter
{
    public function __construct(
        private readonly RateLimitCache $cache,
        private readonly string $prefix = 'capabilities:rate:',
    ) {}

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $storageKey = $this->storageKey($key);
        if ($this->attempts($storageKey) < $maxAttempts) {
            return false;
        }

        if ($this->cache->has($storageKey.':timer')) {
            return true;
        }

        // Window expired — drop the counter so a new window can start.
        $this->cache->forget($storageKey);

        return false;
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        $storageKey = $this->storageKey($key);
        $decay = max(1, $decaySeconds);

        $this->cache->add($storageKey.':timer', time() + $decay, $decay);

        $added = $this->cache->add($storageKey, 0, $decay);
        $hits = $this->cache->increment($storageKey);

        // Race: counter existed without TTL; re-seed so the window is bounded.
        if (! $added && $hits === 1) {
            $this->cache->put($storageKey, 1, $decay);
        }

        return $hits;
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attempts($this->storageKey($key)));
    }

    public function clear(string $key): void
    {
        $storageKey = $this->storageKey($key);
        $this->cache->forget($storageKey);
        $this->cache->forget($storageKey.':timer');
    }

    private function attempts(string $storageKey): int
    {
        return (int) ($this->cache->get($storageKey) ?? 0);
    }

    private function storageKey(string $key): string
    {
        return $this->prefix.$key;
    }
}
