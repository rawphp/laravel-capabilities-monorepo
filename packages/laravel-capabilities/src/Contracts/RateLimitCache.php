<?php

namespace Rawphp\Capabilities\Contracts;

use Illuminate\Contracts\Cache\Repository;
use Rawphp\Capabilities\Support\ArrayRateLimitCache;
use Rawphp\Capabilities\Support\IlluminateRateLimitCache;

/**
 * Narrow shared-cache surface for multi-worker rate limits (L-008 / D-013).
 *
 * Hosts typically wrap {@see Repository} via
 * {@see IlluminateRateLimitCache}. Unit tests use
 * {@see ArrayRateLimitCache} (no Redis/app boot).
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
