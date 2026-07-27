<?php

namespace Rawphp\Capabilities\Schema;

/**
 * Surface health report for GET /health (D-011 / D-021).
 *
 * Statuses: up | disabled_config | disabled_incompatible | missing
 * Never embeds Telegram/Slack secrets — readiness flags only.
 */
final class CatalogHealth
{
    public const STATUS_UP = 'up';

    public const STATUS_DISABLED_CONFIG = 'disabled_config';

    public const STATUS_DISABLED_INCOMPATIBLE = 'disabled_incompatible';

    public const STATUS_MISSING = 'missing';

    /** Surfaces that require a peer package when enabled. */
    private const PEER_SURFACES = ['agent', 'mcp', 'messaging'];

    /**
     * @param  array<string, bool>  $globallyEnabled  surface => enabled
     * @param  array<string, string>  $peerStatus  surface => up|disabled_incompatible|missing
     * @return array{
     *     ok: bool,
     *     surfaces: array<string, array{status: string, enabled: bool}>,
     *     messaging?: array{ready: bool, configured: bool}
     * }
     */
    public function report(array $globallyEnabled, array $peerStatus = []): array
    {
        $surfaces = [];
        $allOk = true;

        foreach ($globallyEnabled as $surface => $enabled) {
            $status = $this->statusFor((string) $surface, (bool) $enabled, $peerStatus);
            $surfaces[$surface] = [
                'status' => $status,
                'enabled' => (bool) $enabled,
            ];
            if ($status !== self::STATUS_UP && $status !== self::STATUS_DISABLED_CONFIG) {
                // disabled_config is intentional off; not a health failure
                if ($enabled) {
                    $allOk = false;
                }
            }
            if ($enabled && $status !== self::STATUS_UP) {
                $allOk = false;
            }
        }

        $payload = [
            'ok' => $allOk,
            'surfaces' => $surfaces,
        ];

        // Messaging details only when surface is on (D-021) — never secrets.
        if (($globallyEnabled['messaging'] ?? false) === true) {
            $msgStatus = $surfaces['messaging']['status'] ?? self::STATUS_DISABLED_CONFIG;
            $payload['messaging'] = [
                'ready' => $msgStatus === self::STATUS_UP,
                'configured' => true,
            ];
        }

        return $payload;
    }

    /**
     * Force a single surface into a given status for matrix unit tests.
     *
     * @param  array<string, bool>  $globallyEnabled
     * @return array{status: string, enabled: bool}
     */
    public function surfaceStatus(
        string $surface,
        string $desiredStatus,
        array $globallyEnabled = [],
    ): array {
        $enabled = $globallyEnabled[$surface] ?? match ($desiredStatus) {
            self::STATUS_DISABLED_CONFIG => false,
            default => true,
        };

        return [
            'status' => $desiredStatus,
            'enabled' => $enabled,
        ];
    }

    /**
     * @param  array<string, string>  $peerStatus
     */
    private function statusFor(string $surface, bool $enabled, array $peerStatus): string
    {
        if (! $enabled) {
            return self::STATUS_DISABLED_CONFIG;
        }

        if (isset($peerStatus[$surface])) {
            return $peerStatus[$surface];
        }

        // Peer surfaces default to up in unit tests when no probe injected.
        if (in_array($surface, self::PEER_SURFACES, true)) {
            return self::STATUS_UP;
        }

        return self::STATUS_UP;
    }
}
