<?php

namespace Rawphp\CapabilitiesMessaging\Boot;

use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramWebhookController;

/**
 * Pure registration plan for MessagingServiceProvider — unit-testable without Laravel boot.
 */
final class MessagingRegistration
{
    public const PUBLISH_TAGS = [
        'capabilities-messaging-config',
        'capabilities-messaging-migrations',
    ];

    /**
     * @param  array<string, mixed>  $config
     * @return array{
     *     config_merged: bool,
     *     config_keys: list<string>,
     *     routes: list<string>,
     *     bindings: list<string>,
     *     publish_tags: list<string>,
     *     secrets_required_at_boot: bool,
     *     telegram_enabled: bool,
     *     health: array<string, mixed>
     * }
     */
    public static function plan(array $config = [], string $appEnv = 'testing'): array
    {
        $cfg = MessagingConfig::fromArray($config, $appEnv);
        $routes = [];

        if ($cfg->telegramEnabled()) {
            $routes[] = 'telegram.webhook';
        }

        return [
            'config_merged' => true,
            'config_keys' => MessagingConfig::TOP_LEVEL_KEYS,
            'routes' => $routes,
            'bindings' => [
                TelegramApprovalNotifier::class,
                TelegramWebhookController::class,
                'ApprovalNotifier.telegram',
            ],
            'publish_tags' => self::PUBLISH_TAGS,
            // D-021: never require TELEGRAM_* at boot / artisan migrate
            'secrets_required_at_boot' => false,
            'telegram_enabled' => $cfg->telegramEnabled(),
            'health' => $cfg->health(),
        ];
    }
}
