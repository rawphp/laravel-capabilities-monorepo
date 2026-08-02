<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Models;

/**
 * Prefix-aware package table names (never host product tables).
 */
final class TableNames
{
    public static function prefix(): string
    {
        $configPath = dirname(__DIR__, 2).'/config/capabilities-ai.php';
        if (is_file($configPath)) {
            /** @var array<string, mixed> $config */
            $config = require $configPath;

            return (string) ($config['table_prefix'] ?? 'capabilities_ai_');
        }

        return 'capabilities_ai_';
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
}
