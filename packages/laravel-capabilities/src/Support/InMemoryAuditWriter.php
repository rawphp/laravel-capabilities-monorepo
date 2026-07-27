<?php

namespace Rawphp\Capabilities\Support;

use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Contracts\Clock;

/**
 * Array-backed audit writer for unit tests (no outbox/DB).
 *
 * Requires an explicit {@see Clock} — missing constructor args fail loudly.
 */
final class InMemoryAuditWriter implements AuditWriter
{
    /** @var list<array<string, mixed>> */
    private array $entries = [];

    public function __construct(
        private readonly Clock $clock,
    ) {}

    public function write(array $entry): void
    {
        if (! isset($entry['recorded_at'])) {
            $entry['recorded_at'] = $this->clock->now()->format(DATE_ATOM);
        }

        $this->entries[] = $entry;
    }

    public function all(): array
    {
        return $this->entries;
    }
}
