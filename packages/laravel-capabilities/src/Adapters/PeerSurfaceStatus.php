<?php

namespace Rawphp\Capabilities\Adapters;

/**
 * Boot-time surface registration outcome for peer-backed surfaces (D-011).
 */
final class PeerSurfaceStatus
{
    public const UP = 'up';

    public const DISABLED_INCOMPATIBLE = 'disabled_incompatible';

    public const DISABLED_CONFIG = 'disabled_config';

    /**
     * @param  list<array{level: string, message: string, context?: array<string, mixed>}>  $logs
     */
    public function __construct(
        public readonly string $surface,
        public readonly string $status,
        public readonly bool $registersTools,
        public readonly array $logs = [],
        public readonly ?string $peer = null,
        public readonly ?string $reason = null,
    ) {}

    public function isUp(): bool
    {
        return $this->status === self::UP;
    }
}
