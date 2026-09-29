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
        $this->links[$telegramUserId] = $link;
    }

    public function findLink(string $telegramUserId): ?array
    {
        return $this->links[$telegramUserId] ?? null;
    }
}
