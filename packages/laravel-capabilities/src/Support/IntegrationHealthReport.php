<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Support;

/**
 * Result of {@see IntegrationHealthChecker} — pure, no HTTP.
 *
 * Distinct from GET …/capabilities/health (CatalogHealth / surface peer status).
 */
final class IntegrationHealthReport
{
    /**
     * @param  list<array{level: 'fail'|'warn'|'ok'|'skip', code: string, message: string}>  $checks
     */
    public function __construct(
        public readonly string $mode,
        public readonly array $checks,
    ) {}

    public function failed(): bool
    {
        foreach ($this->checks as $check) {
            if (($check['level'] ?? null) === 'fail') {
                return true;
            }
        }

        return false;
    }

    public function exitCode(): int
    {
        return $this->failed() ? 1 : 0;
    }
}
