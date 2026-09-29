<?php

namespace Rawphp\CapabilitiesMessaging\Identity;

/**
 * Where {@see IdentityLinker} keeps one-time link codes and code-bound identity links.
 *
 * Codes are issued in the web process and bound on the queue worker, so production storage must
 * be shared across processes and survive restarts ({@see CacheLinkStore}). {@see InMemoryLinkStore}
 * is for unit tests and container-free builds. Allowlist entries stay in config, never here.
 */
interface LinkStore
{
    /**
     * @param  array{user_id: string, tenant_id: string|null, exp: int}  $entry
     */
    public function putCode(string $code, array $entry, int $ttlSeconds): void;

    /**
     * Remove and return a code's entry. Returns it at most once, even to concurrent processes.
     *
     * @return array{user_id: string, tenant_id: string|null, exp: int}|null
     */
    public function takeCode(string $code): ?array;

    /**
     * @param  array{user_id: string, tenant_id: string|null, telegram_user_id: string}  $link
     */
    public function putLink(string $telegramUserId, array $link): void;

    /**
     * @return array{user_id: string, tenant_id: string|null, telegram_user_id: string}|null
     */
    public function findLink(string $telegramUserId): ?array;
}
