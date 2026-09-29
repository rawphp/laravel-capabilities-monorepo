<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Rawphp\CapabilitiesAi\Contracts\ProgressStore;

/**
 * Optional Redis-backed progress store.
 *
 * Requires ext-redis or a predis client passed in. Not used in CI by default.
 * Progress is transient: every append refreshes the turn key's TTL, and since()
 * reads only from the cursor onward.
 */
final class RedisProgressStore implements ProgressStore
{
    public const DEFAULT_TTL_SECONDS = 86400;

    /**
     * @param  object  $redis  Redis client with rPush/lRange/expire (ext-redis or predis-like)
     * @param  int  $ttlSeconds  Key lifetime after the last append
     */
    public function __construct(
        private readonly object $redis,
        private readonly string $keyPrefix = 'capabilities_ai:progress:',
        private readonly int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ) {
        if ($this->ttlSeconds <= 0) {
            throw new \InvalidArgumentException('Progress ttl must be a positive number of seconds');
        }
    }

    public function append(string $turnUlid, array $event): void
    {
        $kind = (string) ($event['kind'] ?? '');
        if ($kind === '') {
            throw new \InvalidArgumentException('Progress event requires kind');
        }

        // No index in the payload: rPush is atomic, so an event's list position
        // is its index. Counting first would let concurrent appends collide.
        $key = $this->keyPrefix.$turnUlid;
        $payload = json_encode([
            'kind' => $kind,
            'data' => $event['data'] ?? null,
            'at' => $event['at'] ?? gmdate('c'),
        ], JSON_THROW_ON_ERROR);

        // is_callable also covers Laravel connection wrappers that proxy via __call.
        if (! is_callable([$this->redis, 'rpush'])) {
            throw new \RuntimeException('Redis client missing rPush');
        }
        if (! is_callable([$this->redis, 'expire'])) {
            throw new \RuntimeException('Redis client missing expire');
        }

        $this->redis->rpush($key, $payload);
        $this->redis->expire($key, $this->ttlSeconds);
    }

    public function since(string $turnUlid, int $cursor = 0): array
    {
        $cursor = max(0, $cursor);
        $rows = $this->lRange($this->keyPrefix.$turnUlid, $cursor);
        $out = [];
        foreach (array_values($rows) as $offset => $raw) {
            /** @var array{kind: string, data?: mixed, at?: string} $decoded */
            $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
            $out[] = [...$decoded, 'index' => $cursor + $offset];
        }

        return $out;
    }

    /** @return list<string> */
    private function lRange(string $key, int $start): array
    {
        if (! is_callable([$this->redis, 'lrange'])) {
            return [];
        }

        /** @var list<string> $rows */
        $rows = $this->redis->lrange($key, $start, -1) ?: [];

        return $rows;
    }
}
