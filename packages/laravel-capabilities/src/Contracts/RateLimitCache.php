<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Narrow shared-cache surface for multi-worker rate limits (L-008 / D-013).
 *
 * Hosts typically wrap {@see \Illuminate\Contracts\Cache\Repository} via
 * {@see \Rawphp\Capabilities\Support\IlluminateRateLimitCache}. Unit tests use
 * {@see \Rawphp\Capabilities\Support\ArrayRateLimitCache} (no Redis/app boot).
 */
interface RateLimitCache
{
    public function get(string $key): mixed;

    public function put(string $key, mixed $value, int $ttlSeconds): void;

    /**
     * Store only when the key is absent. Returns true when the value was written.
     */
    public function add(string $key, mixed $value, int $ttlSeconds): bool;

    public function increment(string $key, int $by = 1): int;

    public function forget(string $key): void;

    public function has(string $key): bool;
}
