<?php

namespace Rawphp\CapabilitiesMessaging;

use RuntimeException;

/**
 * Typed access to capabilities-messaging config (unit-test friendly).
 */
final class MessagingConfig
{
    /** @var list<string> */
    public const TOP_LEVEL_KEYS = [
        'telegram',
        'queue_driver',
        'bot_driver',
        'agent_profile',
        'identity',
        'skip_boot_checks',
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly string $appEnv = 'testing',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'telegram' => [
                'enabled' => true,
                'bot_token' => null,
                'webhook_secret' => null,
                'callback_secret' => null,
                'callback_ttl_seconds' => 900,
            ],
            'queue_driver' => 'auto',
            'bot_driver' => 'auto',
            'agent_profile' => 'support',
            'identity' => [
                'mode' => 'code_link',
                'code_ttl_seconds' => 600,
                'allowlist' => [],
            ],
            'skip_boot_checks' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config = [], string $appEnv = 'testing'): self
    {
        return new self(array_replace_recursive(self::defaults(), $config), $appEnv);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }

    public function hasKey(string $dotPath): bool
    {
        $parts = explode('.', $dotPath);
        $cursor = $this->config;
        foreach ($parts as $part) {
            if (! is_array($cursor) || ! array_key_exists($part, $cursor)) {
                return false;
            }
            $cursor = $cursor[$part];
        }

        return true;
    }

    public function telegramEnabled(): bool
    {
        return (bool) ($this->config['telegram']['enabled'] ?? false);
    }

    public function botToken(): ?string
    {
        $token = $this->config['telegram']['bot_token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function webhookSecret(): ?string
    {
        $secret = $this->config['telegram']['webhook_secret'] ?? null;

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    public function callbackSecret(): string
    {
        $secret = $this->config['telegram']['callback_secret']
            ?? $this->config['telegram']['webhook_secret']
            ?? null;

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('TELEGRAM callback secret is not configured.');
        }

        return $secret;
    }

    public function callbackTtlSeconds(): int
    {
        return (int) ($this->config['telegram']['callback_ttl_seconds'] ?? 900);
    }

    public function agentProfile(): ?string
    {
        $profile = $this->config['agent_profile'] ?? null;

        return is_string($profile) && $profile !== '' ? $profile : null;
    }

    /**
     * Required on first bot traffic (D-008).
     */
    public function requireAgentProfile(): string
    {
        $profile = $this->agentProfile();
        if ($profile === null) {
            throw new RuntimeException(
                'capabilities-messaging.agent_profile is required for bot traffic (D-008).'
            );
        }

        return $profile;
    }

    public function identityMode(): string
    {
        return (string) ($this->config['identity']['mode'] ?? 'code_link');
    }

    public function codeTtlSeconds(): int
    {
        return (int) ($this->config['identity']['code_ttl_seconds'] ?? 600);
    }

    /**
     * @return list<array{telegram_user_id: string, laravel_user_id: string, tenant_id?: string|null}>
     */
    public function allowlist(): array
    {
        $list = $this->config['identity']['allowlist'] ?? [];

        return is_array($list) ? array_values($list) : [];
    }

    public function skipBootChecksRequested(): bool
    {
        return (bool) ($this->config['skip_boot_checks'] ?? false);
    }

    /**
     * CAPABILITIES_SKIP_BOOT_CHECKS is ignored in production (D-021).
     */
    public function maySkipSecretChecks(): bool
    {
        if ($this->appEnv === 'production') {
            return false;
        }

        return $this->skipBootChecksRequested();
    }

    public function appEnv(): string
    {
        return $this->appEnv;
    }

    /**
     * Validate secrets required for webhook / setup / outbound notify (D-021).
     *
     * @throws RuntimeException
     */
    public function requireTelegramSecrets(): void
    {
        if ($this->maySkipSecretChecks()) {
            return;
        }

        if ($this->botToken() === null) {
            throw new RuntimeException(
                'TELEGRAM_BOT_TOKEN is required for messaging traffic (D-021).'
            );
        }

        if ($this->webhookSecret() === null) {
            throw new RuntimeException(
                'TELEGRAM_WEBHOOK_SECRET is required for messaging traffic (D-021).'
            );
        }
    }

    /**
     * Messaging readiness for health endpoints when surface is on.
     *
     * @return array{ready: bool, telegram_enabled: bool, secrets_configured: bool, agent_profile: string|null}
     */
    public function health(): array
    {
        return [
            'ready' => $this->botToken() !== null
                && $this->webhookSecret() !== null
                && $this->agentProfile() !== null,
            'telegram_enabled' => $this->telegramEnabled(),
            'secrets_configured' => $this->botToken() !== null && $this->webhookSecret() !== null,
            'agent_profile' => $this->agentProfile(),
        ];
    }
}
