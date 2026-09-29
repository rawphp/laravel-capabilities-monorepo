<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Models;

use Illuminate\Container\Container;

/**
 * Prefix-aware package table names (never host product tables).
 *
 * The host's config('capabilities-ai.table_prefix') wins (published file, config:cache);
 * the package config file is only the fallback for container-less unit boots.
 */
final class TableNames
{
    private const DEFAULT_PREFIX = 'capabilities_ai_';

    private static ?string $packagePrefix = null;

    public static function prefix(): string
    {
        $app = Container::getInstance();
        if ($app->bound('config')) {
            $prefix = $app->make('config')->get('capabilities-ai.table_prefix');
            if (is_string($prefix) && $prefix !== '') {
                return $prefix;
            }
        }

        return self::$packagePrefix ??= self::packagePrefix();
    }

    public static function conversations(): string
    {
        return self::prefix().'conversations';
    }

    public static function messages(): string
    {
        return self::prefix().'messages';
    }

    public static function turns(): string
    {
        return self::prefix().'turns';
    }

    public static function proposals(): string
    {
        return self::prefix().'proposals';
    }

    private static function packagePrefix(): string
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 2).'/config/capabilities-ai.php';
        $prefix = $config['table_prefix'] ?? null;

        return is_string($prefix) && $prefix !== '' ? $prefix : self::DEFAULT_PREFIX;
    }
}
