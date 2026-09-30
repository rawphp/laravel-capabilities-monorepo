<?php

namespace Rawphp\CapabilitiesMessaging\Identity;

use Rawphp\Capabilities\Contracts\ConversationIdentity;
use Rawphp\Capabilities\Contracts\Metrics;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Support\LinkedUser;
use RuntimeException;

/**
 * Maps Telegram (etc.) user → product principal before agent tools may mutate.
 *
 * Modes: code_link | allowlist. Never trusts client-forged laravel_user_id.
 * Codes and code-bound links live in a {@see LinkStore} (cache-backed in the container, so a
 * code issued in the web process binds on the queue worker); allowlist entries stay in config.
 * Stored links resolve only in code_link mode: switching to allowlist leaves only config entries.
 * A product user has at most one stored link per tenant: binding from another Telegram account
 * revokes the earlier one. unlink() / unlinkUser() revoke a single link in any mode.
 * Denials (forged bind, cross-tenant resolve) are counted on the optional core Metrics contract (D-019).
 */
final class IdentityLinker implements ConversationIdentity
{
    public const METRIC_BIND_DENIED = 'messaging_identity_bind_denied_total';

    /**
     * Allowlist entries from config (never written to the store, so removing one revokes it).
     *
     * @var array<string, array{user_id: string, tenant_id: string|null, telegram_user_id: string}>
     */
    private array $allowlisted = [];

    private readonly LinkStore $store;

    /**
     * Optional user factory for allowlist / code bind: (userId, tenantId) => object
     *
     * @var callable(string, ?string): object|null
     */
    private $userFactory;

    public function __construct(
        private readonly MessagingConfig $config = new MessagingConfig([]),
        ?callable $userFactory = null,
        private readonly ?Metrics $metrics = null,
        ?LinkStore $store = null,
    ) {
        $this->store = $store ?? new InMemoryLinkStore;
        $this->userFactory = $userFactory ?? static fn (string $id, ?string $tenantId): LinkedUser => new LinkedUser(
            id: $id,
            tenantId: $tenantId,
        );

        $this->config->requireUniqueAllowlist();

        foreach ($this->config->allowlist() as $entry) {
            $tg = (string) ($entry['telegram_user_id'] ?? '');
            $uid = (string) ($entry['laravel_user_id'] ?? '');
            if ($tg === '' || $uid === '') {
                continue;
            }
            $this->allowlisted[$tg] = [
                'user_id' => $uid,
                'tenant_id' => isset($entry['tenant_id']) ? (string) $entry['tenant_id'] : null,
                'telegram_user_id' => $tg,
            ];
        }
    }

    /**
     * Issue a one-time link code for a Laravel user (code_link mode).
     */
    public function issueLinkCode(string $laravelUserId, ?string $tenantId = null, ?int $now = null): string
    {
        $now ??= time();
        $ttl = $this->config->codeTtlSeconds();
        $code = bin2hex(random_bytes(8));
        $this->store->putCode($code, [
            'user_id' => $laravelUserId,
            'tenant_id' => $tenantId,
            'exp' => $now + $ttl,
        ], $ttl);

        return $code;
    }

    /**
     * Bind Telegram user via previously issued code. Rejects expired/reused/forged codes,
     * and refuses outright unless identity.mode is code_link (allowlist must not be bypassed).
     */
    public function bindWithCode(string $telegramUserId, string $code, ?int $now = null): ?object
    {
        if ($this->config->identityMode() !== 'code_link') {
            return null;
        }

        $now ??= time();
        // Taken before the expiry check: a code is spent on first presentation either way.
        $entry = $this->store->takeCode($code);
        if ($entry === null || $entry['exp'] < $now) {
            return null;
        }

        $this->storeLink($telegramUserId, $entry['user_id'], $entry['tenant_id']);

        return ($this->userFactory)($entry['user_id'], $entry['tenant_id']);
    }

    /**
     * Explicit link (tests / admin). Not available from untrusted webhook fields.
     * Refused outside code_link mode, where stored links would never resolve.
     */
    public function link(string $telegramUserId, string $laravelUserId, ?string $tenantId = null): object
    {
        if ($this->config->identityMode() !== 'code_link') {
            throw new RuntimeException('Explicit identity links need identity.mode code_link (MSG-002).');
        }

        $this->storeLink($telegramUserId, $laravelUserId, $tenantId);

        return ($this->userFactory)($laravelUserId, $tenantId);
    }

    /**
     * Revoke a Telegram account's stored link (lost phone, hijacked account). Works in any mode;
     * allowlist entries live in config and are removed there.
     */
    public function unlink(string $telegramUserId): void
    {
        $this->store->forgetLink($telegramUserId);
    }

    /**
     * Revoke the stored link of a product user (host "disconnect Telegram", offboarding).
     */
    public function unlinkUser(string $laravelUserId, ?string $tenantId = null): void
    {
        $telegramUserId = $this->store->findTelegramUserId($laravelUserId, $tenantId);
        if ($telegramUserId !== null) {
            $this->store->forgetLink($telegramUserId);
        }
    }

    /**
     * One stored link per product user and tenant: a new Telegram account replaces the old one.
     */
    private function storeLink(string $telegramUserId, string $laravelUserId, ?string $tenantId): void
    {
        $previous = $this->store->findTelegramUserId($laravelUserId, $tenantId);
        if ($previous !== null && $previous !== $telegramUserId) {
            $this->store->forgetLink($previous);
        }

        $this->store->putLink($telegramUserId, [
            'user_id' => $laravelUserId,
            'tenant_id' => $tenantId,
            'telegram_user_id' => $telegramUserId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $externalIdentity  channel + telegram_user_id (server-derived from update)
     */
    public function resolve(array $externalIdentity): ?object
    {
        $telegramUserId = $this->extractTelegramUserId($externalIdentity);
        if ($telegramUserId === null) {
            return null;
        }

        // Forged laravel_user_id / tenant_id in payload must not escalate.
        if (isset($externalIdentity['laravel_user_id']) || isset($externalIdentity['forged_laravel_user_id'])) {
            // Ignore client-claimed Laravel id entirely.
        }

        $link = $this->findLink($telegramUserId);
        if ($link === null) {
            return null;
        }

        $expectedTenant = $externalIdentity['expected_tenant_id'] ?? $externalIdentity['tenant_id'] ?? null;
        if ($expectedTenant !== null && $link['tenant_id'] !== null && (string) $expectedTenant !== (string) $link['tenant_id']) {
            $this->metrics?->increment(self::METRIC_BIND_DENIED, 1, ['reason' => 'tenant_mismatch']);

            return null;
        }

        $user = ($this->userFactory)($link['user_id'], $link['tenant_id']);
        if ($user instanceof LinkedUser && $user->telegramUserId === null) {
            return new LinkedUser(
                id: $user->id,
                tenantId: $user->tenantId,
                telegramUserId: $telegramUserId,
                name: $user->name,
            );
        }

        return $user;
    }

    public function isLinked(string $telegramUserId): bool
    {
        return $this->findLink($telegramUserId) !== null;
    }

    /**
     * In code_link mode a code-bound or explicit link wins over an allowlist entry for the same
     * Telegram user. In any other mode stored links are ignored, so switching to allowlist revokes
     * every code-bound user without touching the store (fail closed).
     *
     * @return array{user_id: string, tenant_id: string|null, telegram_user_id: string}|null
     */
    private function findLink(string $telegramUserId): ?array
    {
        $stored = $this->config->identityMode() === 'code_link' ? $this->store->findLink($telegramUserId) : null;

        return $stored ?? $this->allowlisted[$telegramUserId] ?? null;
    }

    public function canUseTools(?object $user): bool
    {
        return $user !== null;
    }

    /**
     * Reject forged identity claims that try to bind without code flow.
     *
     * @param  array<string, mixed>  $payload
     */
    public function rejectForgedBind(array $payload): never
    {
        $this->metrics?->increment(self::METRIC_BIND_DENIED, 1, ['reason' => 'forged_bind']);

        throw new RuntimeException(
            'Forged identity bind rejected: use code_link or allowlist only (MSG-002).'
        );
    }

    /**
     * @param  array<string, mixed>  $externalIdentity
     */
    private function extractTelegramUserId(array $externalIdentity): ?string
    {
        if (isset($externalIdentity['telegram_user_id']) && is_scalar($externalIdentity['telegram_user_id'])) {
            return (string) $externalIdentity['telegram_user_id'];
        }

        if (isset($externalIdentity['from']['id']) && is_scalar($externalIdentity['from']['id'])) {
            return (string) $externalIdentity['from']['id'];
        }

        if (isset($externalIdentity['user_id']) && is_scalar($externalIdentity['user_id'])) {
            return (string) $externalIdentity['user_id'];
        }

        return null;
    }
}
