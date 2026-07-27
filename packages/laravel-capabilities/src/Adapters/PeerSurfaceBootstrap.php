<?php

namespace Rawphp\Capabilities\Adapters;

/**
 * Fail-closed / soft-disable registration for agent + mcp peers (D-011).
 *
 * Never half-registers tools: either tools are allowed (status up) or zero tools.
 */
final class PeerSurfaceBootstrap
{
    public function __construct(
        private readonly PeerVersionProbe $probe,
    ) {}

    /**
     * @param  array{
     *     enabled?: bool,
     *     require_package?: bool,
     *     on_incompatible?: string
     * }  $surfaceConfig
     */
    public function evaluate(string $surface, string $peer, array $surfaceConfig): PeerSurfaceStatus
    {
        $enabled = (bool) ($surfaceConfig['enabled'] ?? true);
        if (! $enabled) {
            return new PeerSurfaceStatus(
                surface: $surface,
                status: PeerSurfaceStatus::DISABLED_CONFIG,
                registersTools: false,
                peer: $peer,
                reason: 'disabled_config',
            );
        }

        $requirePackage = (bool) ($surfaceConfig['require_package'] ?? true);
        $mode = (string) ($surfaceConfig['on_incompatible'] ?? 'fail');
        $installed = $this->probe->isInstalled($peer);
        $compatible = $this->probe->isCompatible($peer);

        if ($installed && $compatible) {
            return new PeerSurfaceStatus(
                surface: $surface,
                status: PeerSurfaceStatus::UP,
                registersTools: true,
                peer: $peer,
                reason: null,
            );
        }

        if (! $requirePackage && ! $installed) {
            // Optional peer missing: soft disable without exception.
            return new PeerSurfaceStatus(
                surface: $surface,
                status: PeerSurfaceStatus::DISABLED_INCOMPATIBLE,
                registersTools: false,
                logs: [[
                    'level' => 'critical',
                    'message' => sprintf(
                        'capabilities.surface.disabled peer=%s reason=missing surface=%s adapter_api=%d',
                        $peer,
                        $surface,
                        AdapterApi::CURRENT,
                    ),
                    'context' => [
                        'peer' => $peer,
                        'surface' => $surface,
                        'reason' => 'missing',
                        'adapter_api' => AdapterApi::CURRENT,
                    ],
                ]],
                peer: $peer,
                reason: 'missing_optional',
            );
        }

        $reason = ! $installed ? 'missing' : 'incompatible';
        $message = sprintf(
            'capabilities.surface.disabled peer=%s reason=%s surface=%s adapter_api=%d',
            $peer,
            $reason,
            $surface,
            AdapterApi::CURRENT,
        );
        $log = [
            'level' => 'critical',
            'message' => $message,
            'context' => [
                'peer' => $peer,
                'surface' => $surface,
                'reason' => $reason,
                'installed' => $installed,
                'compatible' => $compatible,
                'adapter_api' => AdapterApi::CURRENT,
                'installed_version' => $this->probe->installedVersion($peer),
            ],
        ];

        if ($mode === 'disable') {
            return new PeerSurfaceStatus(
                surface: $surface,
                status: PeerSurfaceStatus::DISABLED_INCOMPATIBLE,
                registersTools: false,
                logs: [$log],
                peer: $peer,
                reason: $reason,
            );
        }

        // on_incompatible = fail (default)
        if (! $installed) {
            throw PeerIncompatibleException::missing($peer, $surface);
        }

        throw PeerIncompatibleException::incompatible(
            $peer,
            $surface,
            $this->probe->installedVersion($peer),
        );
    }
}
