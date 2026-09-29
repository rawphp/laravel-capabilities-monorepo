<?php

namespace Rawphp\CapabilitiesMessaging\Identity;

use Illuminate\Contracts\Cache\Repository;

/**
 * Production {@see LinkStore} on the host's Laravel cache, shared by web and queue workers.
 *
 * Codes expire with the cache TTL (identity.code_ttl_seconds). A code is claimed with an atomic
 * `add()` before it is forgotten, so two workers reading it at once bind it at most once.
 * Links are stored without expiry: use a persistent cache store (redis, database) that deploys
 * do not flush. A lost link fails closed — the user links again.
 */
final class CacheLinkStore implements LinkStore
{
    public const PREFIX = 'capabilities-messaging:identity:';

    public function __construct(
        private readonly Repository $cache,
    ) {}

    public function putCode(string $code, array $entry, int $ttlSeconds): void
    {
        $ttl = max(1, $ttlSeconds);
        $this->cache->put(self::PREFIX.'code:'.$code, ['entry' => $entry, 'ttl' => $ttl], $ttl);
    }

    public function takeCode(string $code): ?array
    {
        $key = self::PREFIX.'code:'.$code;
        $stored = $this->cache->get($key);
        if (! is_array($stored) || ! is_array($stored['entry'] ?? null)) {
            return null;
        }

        if (! $this->cache->add($key.':claimed', true, max(1, (int) ($stored['ttl'] ?? 1)))) {
            return null;
        }
        $this->cache->forget($key);

        return $stored['entry'];
    }

    public function putLink(string $telegramUserId, array $link): void
    {
        $this->forgetLink($telegramUserId);
        $this->cache->forever(self::PREFIX.'link:'.$telegramUserId, $link);
        $this->cache->forever(self::userKey($link['user_id'], $link['tenant_id']), $telegramUserId);
    }

    public function findLink(string $telegramUserId): ?array
    {
        $link = $this->cache->get(self::PREFIX.'link:'.$telegramUserId);

        return is_array($link) ? $link : null;
    }

    public function forgetLink(string $telegramUserId): void
    {
        $link = $this->findLink($telegramUserId);
        $this->cache->forget(self::PREFIX.'link:'.$telegramUserId);

        if ($link !== null && $this->findTelegramUserId($link['user_id'], $link['tenant_id']) === $telegramUserId) {
            $this->cache->forget(self::userKey($link['user_id'], $link['tenant_id']));
        }
    }

    public function findTelegramUserId(string $userId, ?string $tenantId): ?string
    {
        $telegramUserId = $this->cache->get(self::userKey($userId, $tenantId));

        return is_string($telegramUserId) ? $telegramUserId : null;
    }

    private static function userKey(string $userId, ?string $tenantId): string
    {
        return self::PREFIX.'user:'.rawurlencode($tenantId ?? '').':'.rawurlencode($userId);
    }
}
