<?php

namespace Rawphp\CapabilitiesMessaging\Boot;

use Rawphp\CapabilitiesMessaging\MessagingConfig;
use RuntimeException;

/**
 * messaging:telegram-setup validation (D-021) — fails loudly without secrets.
 *
 * With a host user lookup, also fails loudly on identity.allowlist entries whose
 * laravel_user_id does not resolve, instead of "linking" a user that does not exist.
 */
final class TelegramSetup
{
    /**
     * @param  (callable(string $laravelUserId, ?string $tenantId): ?object)|null  $userLookup  host user resolver (e.g. fn ($id) => User::find($id))
     * @return array{ok: bool, message: string}
     */
    public static function validate(MessagingConfig $config, ?callable $userLookup = null): array
    {
        try {
            $config->requireTelegramSecrets();
            $config->requireAgentProfile();
            $config->requireIdentityMode();
            $config->requireUniqueAllowlist();
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $problems = self::allowlistProblems($config, $userLookup);
        if ($problems !== []) {
            return ['ok' => false, 'message' => implode(' ', $problems)];
        }

        return ['ok' => true, 'message' => 'Telegram messaging secrets OK'];
    }

    /**
     * @param  (callable(string $laravelUserId, ?string $tenantId): ?object)|null  $userLookup
     *
     * @throws RuntimeException
     */
    public static function runOrFail(MessagingConfig $config, ?callable $userLookup = null): void
    {
        $result = self::validate($config, $userLookup);
        if (! $result['ok']) {
            throw new RuntimeException($result['message']);
        }
    }

    /**
     * @param  (callable(string $laravelUserId, ?string $tenantId): ?object)|null  $userLookup
     * @return list<string>
     */
    private static function allowlistProblems(MessagingConfig $config, ?callable $userLookup): array
    {
        $problems = [];
        foreach ($config->allowlist() as $i => $entry) {
            $tg = (string) ($entry['telegram_user_id'] ?? '');
            $uid = (string) ($entry['laravel_user_id'] ?? '');
            if ($tg === '' || $uid === '') {
                $problems[] = "identity.allowlist[{$i}] needs both telegram_user_id and laravel_user_id (MSG-002).";

                continue;
            }

            $tenantId = isset($entry['tenant_id']) ? (string) $entry['tenant_id'] : null;
            if ($userLookup !== null && $userLookup($uid, $tenantId) === null) {
                $problems[] = "identity.allowlist[{$i}]: laravel_user_id \"{$uid}\" (telegram_user_id \"{$tg}\") "
                    .'does not resolve to a user (MSG-002).';
            }
        }

        return $problems;
    }
}
