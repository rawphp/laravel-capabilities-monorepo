<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi;

/**
 * Package identity marker for monorepo smoke tests and autoload verification.
 */
final class Package
{
    public static function name(): string
    {
        return 'rawphp/laravel-capabilities-ai';
    }
}
