<?php

namespace Rawphp\Capabilities\Boot;

/**
 * Canonical surface identifiers and default enablement (SURF-002 / D-007).
 */
final class SurfaceNames
{
    public const AGENT = 'agent';

    public const MCP = 'mcp';

    public const HTTP = 'http';

    public const CLI = 'cli';

    public const JOB = 'job';

    public const ARTISAN = 'artisan';

    public const MESSAGING = 'messaging';

    /** @var list<string> */
    public const ALL = [
        self::AGENT,
        self::MCP,
        self::HTTP,
        self::CLI,
        self::JOB,
        self::ARTISAN,
        self::MESSAGING,
    ];

    /**
     * Invoke surfaces that default to enabled in core package config.
     *
     * @var list<string>
     */
    public const INVOKE_DEFAULT_ON = [
        self::AGENT,
        self::MCP,
        self::HTTP,
        self::CLI,
        self::JOB,
        self::ARTISAN,
    ];

    /**
     * Surfaces that require a peer Composer package when enabled + require_package.
     *
     * @var array<string, string>
     */
    public const PEER_PACKAGES = [
        self::AGENT => 'laravel/ai',
        self::MCP => 'laravel/mcp',
        self::MESSAGING => 'rawphp/laravel-capabilities-messaging',
    ];

    /**
     * Default global enable flags matching config/capabilities.php.
     *
     * @return array<string, bool>
     */
    public static function defaultEnabledMap(): array
    {
        $map = [];
        foreach (self::ALL as $surface) {
            $map[$surface] = $surface !== self::MESSAGING;
        }

        return $map;
    }

    public static function isKnown(string $surface): bool
    {
        return in_array($surface, self::ALL, true);
    }
}
