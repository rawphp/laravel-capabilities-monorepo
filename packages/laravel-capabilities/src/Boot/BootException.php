<?php

namespace Rawphp\Capabilities\Boot;

use RuntimeException;

/**
 * Fail-closed boot rule violation (SURF-004 / D-011 / D-007).
 */
final class BootException extends RuntimeException
{
    public static function cliRequiresHttp(): self
    {
        return new self(
            'Surface "cli" requires surface "http" to be enabled (SURF-004). CLI is an HTTP client of the capability API.'
        );
    }

    public static function messagingRequiresAgent(): self
    {
        return new self(
            'Surface "messaging" requires surface "agent" to be enabled (SURF-004 / D-007).'
        );
    }

    public static function messagingRequiresPackage(): self
    {
        return new self(
            'Surface "messaging" is enabled but rawphp/laravel-capabilities-messaging is not installed (D-007).'
        );
    }

    public static function skipBootChecksForbiddenInProduction(): self
    {
        return new self(
            'CAPABILITIES_SKIP_BOOT_CHECKS is forbidden when APP_ENV=production (D-021).'
        );
    }

    public static function unknownDriver(string $kind, string $requested): self
    {
        return new self(
            "Unknown capabilities config driver for [{$kind}]: \"{$requested}\". Supported: memory, in_memory, array, database."
        );
    }

    public static function unknownAuditMode(string $mode): self
    {
        return new self(
            "Unknown capabilities audit.mode: \"{$mode}\". Supported: best_effort, strict."
        );
    }

    public static function missingDatabaseConnection(string $table): self
    {
        return new self(
            "Database driver for table [{$table}] requires an Illuminate database connection "
            .'(inject ConnectionInterface, bind db.connection, or set approval.connection / '
            .'idempotency.connection). Refusing silent ArrayTableGateway fallback (REQ-051).'
        );
    }
}
