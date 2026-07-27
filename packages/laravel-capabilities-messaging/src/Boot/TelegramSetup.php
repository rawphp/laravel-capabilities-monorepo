<?php

namespace Rawphp\CapabilitiesMessaging\Boot;

use Rawphp\CapabilitiesMessaging\MessagingConfig;
use RuntimeException;

/**
 * messaging:telegram-setup validation (D-021) — fails loudly without secrets.
 */
final class TelegramSetup
{
    /**
     * @return array{ok: bool, message: string}
     */
    public static function validate(MessagingConfig $config): array
    {
        try {
            $config->requireTelegramSecrets();
            $config->requireAgentProfile();
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return ['ok' => true, 'message' => 'Telegram messaging secrets OK'];
    }

    /**
     * @throws RuntimeException
     */
    public static function runOrFail(MessagingConfig $config): void
    {
        $result = self::validate($config);
        if (! $result['ok']) {
            throw new RuntimeException($result['message']);
        }
    }
}
