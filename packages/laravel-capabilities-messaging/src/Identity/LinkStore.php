<?php

namespace Rawphp\CapabilitiesMessaging\Identity;

/**
 * Where {@see IdentityLinker} keeps one-time link codes and code-bound identity links.
 *
 * Codes are issued in the web process and bound on the queue worker, so production storage must
 * be shared across processes and survive restarts ({@see CacheLinkStore}). {@see InMemoryLinkStore}
 * is for unit tests and container-free builds. Allowlist entries stay in config, never here.
 *
 * Links are indexed both ways: Telegram user → link, and (tenant, product user) → Telegram user,
 * so a host can revoke a product user's link without knowing their Telegram id.
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
     * Store a link, replacing any earlier link of this Telegram user (and its reverse index entry).
     *
     * @param  array{user_id: string, tenant_id: string|null, telegram_user_id: string}  $link
     */
    public function putLink(string $telegramUserId, array $link): void;

    /**
     * @return array{user_id: string, tenant_id: string|null, telegram_user_id: string}|null
     */
    public function findLink(string $telegramUserId): ?array;

    /**
     * Remove a Telegram user's link and its reverse index entry. Unknown ids are a no-op.
     */
    public function forgetLink(string $telegramUserId): void;

    /**
     * The Telegram user currently linked to a product user in a tenant, if any.
     */
    public function findTelegramUserId(string $userId, ?string $tenantId): ?string;
}
