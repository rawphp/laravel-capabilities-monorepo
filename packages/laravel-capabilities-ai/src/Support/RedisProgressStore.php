<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Rawphp\CapabilitiesAi\Contracts\ProgressStore;

/**
 * Optional Redis-backed progress store.
 *
 * Requires ext-redis or a predis client passed in. Not used in CI by default.
 */
final class RedisProgressStore implements ProgressStore
{
    /**
     * @param  object  $redis  Redis client with rPush/lRange (ext-redis or predis-like)
     */
    public function __construct(
        private readonly object $redis,
        private readonly string $keyPrefix = 'capabilities_ai:progress:',
    ) {}

    public function append(string $turnUlid, array $event): void
    {
        $kind = (string) ($event['kind'] ?? '');
        if ($kind === '') {
            throw new \InvalidArgumentException('Progress event requires kind');
        }

        $key = $this->keyPrefix.$turnUlid;
        $existing = $this->lRange($key);
        $index = count($existing);
        $payload = json_encode([
            'kind' => $kind,
            'data' => $event['data'] ?? null,
            'at' => $event['at'] ?? gmdate('c'),
            'index' => $index,
        ], JSON_THROW_ON_ERROR);

        if (method_exists($this->redis, 'rPush')) {
            $this->redis->rPush($key, $payload);
        } elseif (method_exists($this->redis, 'rpush')) {
            $this->redis->rpush($key, $payload);
        } else {
            throw new \RuntimeException('Redis client missing rPush');
        }
    }

    public function since(string $turnUlid, int $cursor = 0): array
    {
        $key = $this->keyPrefix.$turnUlid;
        $rows = $this->lRange($key);
        $out = [];
        foreach ($rows as $raw) {
            /** @var array{kind: string, data?: mixed, at?: string, index: int} $decoded */
            $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
            if (($decoded['index'] ?? 0) >= $cursor) {
                $out[] = $decoded;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function lRange(string $key): array
    {
        if (method_exists($this->redis, 'lRange')) {
            /** @var list<string> $rows */
            $rows = $this->redis->lRange($key, 0, -1) ?: [];

            return $rows;
        }
        if (method_exists($this->redis, 'lrange')) {
            /** @var list<string> $rows */
            $rows = $this->redis->lrange($key, 0, -1) ?: [];

            return $rows;
        }

        return [];
    }
}
