<?php

namespace Rawphp\Capabilities\Support;

use Rawphp\Capabilities\Contracts\AuditWriter;
use RuntimeException;

/**
 * Audit writer that always throws — for best_effort / strict mode unit tests.
 */
final class FailingAuditWriter implements AuditWriter
{
    public function __construct(
        private readonly string $message = 'disk full',
    ) {}

    public function write(array $entry): void
    {
        throw new RuntimeException($this->message);
    }

    public function all(): array
    {
        return [];
    }
}
