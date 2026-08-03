<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi;

/**
 * Package identity + shared package defaults.
 */
final class Package
{
    /**
     * Default claim / job timeout seconds (capabilities-ai.claim_ttl).
     * Single definition for config, RunTurnJob, ConversationService, and SP clamp.
     */
    public const DEFAULT_CLAIM_TTL = 120;

    public static function name(): string
    {
        return 'rawphp/laravel-capabilities-ai';
    }
}
