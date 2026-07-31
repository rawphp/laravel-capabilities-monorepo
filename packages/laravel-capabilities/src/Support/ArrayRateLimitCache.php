<?php

namespace Rawphp\Capabilities\Support;

use Rawphp\Capabilities\Contracts\RateLimitCache;

/**
 * Process-local {@see RateLimitCache} for unit tests (no Redis / Laravel app).
 *
 * TTL is enforced with wall-clock expiry so decay windows can be asserted
 * without Illuminate Cache or a real store.
 */
final class ArrayRateLimitCache implements RateLimitCache
{
    /**
     * @var array<string, array{value: mixed, expires_at: ?float}>
     */
    private array $items = [];

    public function get(string $key): mixed
    {
        $this->purgeIfExpired($key);

        return $this->items[$key]['value'] ?? null;
    }

    public function put(string $key, mixed $value, int $ttlSeconds): void
    {
        $this->items[$key] = [
            'value' => $value,
            'expires_at' => $ttlSeconds > 0 ? microtime(true) + $ttlSeconds : null,
        ];
    }

    public function add(string $key, mixed $value, int $ttlSeconds): bool
    {
        $this->purgeIfExpired($key);
        if (array_key_exists($key, $this->items)) {
            return false;
        }
        $this->put($key, $value, $ttlSeconds);

        return true;
    }

    public function increment(string $key, int $by = 1): int
    {
        $this->purgeIfExpired($key);
        $current = (int) ($this->items[$key]['value'] ?? 0);
        $next = $current + $by;
        $expires = $this->items[$key]['expires_at'] ?? null;
        $this->items[$key] = [
            'value' => $next,
            'expires_at' => $expires,
        ];

        return $next;
    }

    public function forget(string $key): void
    {
        unset($this->items[$key]);
    }

    public function has(string $key): bool
    {
        $this->purgeIfExpired($key);

        return array_key_exists($key, $this->items);
    }

    private function purgeIfExpired(string $key): void
    {
        if (! isset($this->items[$key])) {
            return;
        }
        $expires = $this->items[$key]['expires_at'];
        if ($expires !== null && microtime(true) >= $expires) {
            unset($this->items[$key]);
        }
    }
}
