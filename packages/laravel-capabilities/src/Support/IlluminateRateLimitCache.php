<?php

namespace Rawphp\Capabilities\Support;

use Illuminate\Contracts\Cache\Repository;
use Rawphp\Capabilities\Contracts\RateLimitCache;

/**
 * Adapts Illuminate {@see Repository} to {@see RateLimitCache} for production
 * multi-worker rate limits (L-008). No Redis client is embedded here — hosts
 * configure the cache store (redis, memcached, database, …) via Laravel.
 */
final class IlluminateRateLimitCache implements RateLimitCache
{
    public function __construct(
        private readonly Repository $repository,
    ) {}

    public function get(string $key): mixed
    {
        return $this->repository->get($key);
    }

    public function put(string $key, mixed $value, int $ttlSeconds): void
    {
        $this->repository->put($key, $value, $ttlSeconds);
    }

    public function add(string $key, mixed $value, int $ttlSeconds): bool
    {
        return (bool) $this->repository->add($key, $value, $ttlSeconds);
    }

    public function increment(string $key, int $by = 1): int
    {
        $result = $this->repository->increment($key, $by);

        return is_int($result) ? $result : (int) $result;
    }

    public function forget(string $key): void
    {
        $this->repository->forget($key);
    }

    public function has(string $key): bool
    {
        return $this->repository->has($key);
    }
}
