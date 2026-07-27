<?php

namespace Rawphp\CapabilitiesMessaging\Identity;

use Rawphp\Capabilities\Contracts\ConversationIdentity;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Support\LinkedUser;
use RuntimeException;

/**
 * Maps Telegram (etc.) user → product principal before agent tools may mutate.
 *
 * Modes: code_link | allowlist. Never trusts client-forged laravel_user_id.
 */
final class IdentityLinker implements ConversationIdentity
{
    /** @var array<string, array{user_id: string, tenant_id: string|null, telegram_user_id: string}> */
    private array $links = [];

    /** @var array<string, array{user_id: string, tenant_id: string|null, exp: int, used: bool}> */
    private array $codes = [];

    /**
     * Optional user factory for allowlist / code bind: (userId, tenantId) => object
     *
     * @var callable(string, ?string): object|null
     */
    private $userFactory;

    public function __construct(
        private readonly MessagingConfig $config = new MessagingConfig([]),
        ?callable $userFactory = null,
    ) {
        $this->userFactory = $userFactory ?? static fn (string $id, ?string $tenantId): LinkedUser => new LinkedUser(
            id: $id,
            tenantId: $tenantId,
        );

        foreach ($this->config->allowlist() as $entry) {
            $tg = (string) ($entry['telegram_user_id'] ?? '');
            $uid = (string) ($entry['laravel_user_id'] ?? '');
            if ($tg === '' || $uid === '') {
                continue;
            }
            $this->links[$tg] = [
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
        $code = bin2hex(random_bytes(8));
        $this->codes[$code] = [
            'user_id' => $laravelUserId,
            'tenant_id' => $tenantId,
            'exp' => $now + $this->config->codeTtlSeconds(),
            'used' => false,
        ];

        return $code;
    }

    /**
     * Bind Telegram user via previously issued code. Rejects expired/reused/forged codes.
     */
    public function bindWithCode(string $telegramUserId, string $code, ?int $now = null): ?object
    {
        $now ??= time();
        $entry = $this->codes[$code] ?? null;
        if ($entry === null || $entry['used'] || $entry['exp'] < $now) {
            return null;
        }

        $this->codes[$code]['used'] = true;
        $this->links[$telegramUserId] = [
            'user_id' => $entry['user_id'],
            'tenant_id' => $entry['tenant_id'],
            'telegram_user_id' => $telegramUserId,
        ];

        return ($this->userFactory)($entry['user_id'], $entry['tenant_id']);
    }

    /**
     * Explicit link (tests / admin). Not available from untrusted webhook fields.
     */
    public function link(string $telegramUserId, string $laravelUserId, ?string $tenantId = null): object
    {
        $this->links[$telegramUserId] = [
            'user_id' => $laravelUserId,
            'tenant_id' => $tenantId,
            'telegram_user_id' => $telegramUserId,
        ];

        return ($this->userFactory)($laravelUserId, $tenantId);
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

        $link = $this->links[$telegramUserId] ?? null;
        if ($link === null) {
            return null;
        }

        $expectedTenant = $externalIdentity['expected_tenant_id'] ?? $externalIdentity['tenant_id'] ?? null;
        if ($expectedTenant !== null && $link['tenant_id'] !== null && (string) $expectedTenant !== (string) $link['tenant_id']) {
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
        return isset($this->links[$telegramUserId]);
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
