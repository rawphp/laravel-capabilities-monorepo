<?php

namespace Rawphp\CapabilitiesMessaging\Identity;

/**
 * Process-local {@see LinkStore} for unit tests and container-free builds.
 *
 * Not for production: a code issued in the web process is invisible to the queue worker.
 * Expiry is enforced by {@see IdentityLinker} against the entry's `exp`.
 */
final class InMemoryLinkStore implements LinkStore
{
    /** @var array<string, array{user_id: string, tenant_id: string|null, exp: int}> */
    private array $codes = [];

    /** @var array<string, array{user_id: string, tenant_id: string|null, telegram_user_id: string}> */
    private array $links = [];

    /** @var array<string, string> reverse index: tenant + product user → Telegram user */
    private array $users = [];

    public function putCode(string $code, array $entry, int $ttlSeconds): void
    {
        $this->codes[$code] = $entry;
    }

    public function takeCode(string $code): ?array
    {
        $entry = $this->codes[$code] ?? null;
        unset($this->codes[$code]);

        return $entry;
    }

    public function putLink(string $telegramUserId, array $link): void
    {
        $this->forgetLink($telegramUserId);
        $this->links[$telegramUserId] = $link;
        $this->users[self::userKey($link['user_id'], $link['tenant_id'])] = $telegramUserId;
    }

    public function findLink(string $telegramUserId): ?array
    {
        return $this->links[$telegramUserId] ?? null;
    }

    public function forgetLink(string $telegramUserId): void
    {
        $link = $this->links[$telegramUserId] ?? null;
        unset($this->links[$telegramUserId]);

        if ($link !== null && $this->findTelegramUserId($link['user_id'], $link['tenant_id']) === $telegramUserId) {
            unset($this->users[self::userKey($link['user_id'], $link['tenant_id'])]);
        }
    }

    public function findTelegramUserId(string $userId, ?string $tenantId): ?string
    {
        return $this->users[self::userKey($userId, $tenantId)] ?? null;
    }

    private static function userKey(string $userId, ?string $tenantId): string
    {
        return rawurlencode($tenantId ?? '').':'.rawurlencode($userId);
    }
}
