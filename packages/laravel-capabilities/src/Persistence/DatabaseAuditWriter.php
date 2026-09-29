<?php

namespace Rawphp\Capabilities\Persistence;

use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Contracts\Clock;

/**
 * First-party durable {@see AuditWriter} for `audit.driver = database` (D-010).
 *
 * Every entry becomes one row in `capabilities_audit_outbox`
 * ({@see MigrationCatalog::TABLE_AUDIT_OUTBOX}): the row **is** the audit record. It is
 * inserted `pending` with `available_at = now` so a host drain job can forward rows to a
 * long-term sink and mark them `completed` (the D-010 outbox pattern); without a drain the
 * table itself is the queryable audit log. Failures propagate — the pipeline's audit mode
 * (best_effort vs strict) decides what the caller sees.
 *
 * Production: {@see QueryTableGateway} on the outbox table. Tests: {@see ArrayTableGateway}.
 */
final class DatabaseAuditWriter implements AuditWriter
{
    public const STATUS_PENDING = 'pending';

    public function __construct(
        private readonly TableGateway $table,
        private readonly Clock $clock,
    ) {}

    public function write(array $entry): void
    {
        $now = $this->clock->now()->format(DATE_ATOM);
        $entry['recorded_at'] ??= $now;

        $this->table->insert([
            'event' => (string) ($entry['event'] ?? 'capability.unknown'),
            'capability_name' => self::stringOrNull($entry['capability_name'] ?? $entry['name'] ?? $entry['capability'] ?? null),
            'tenant_id' => self::stringOrNull($entry['tenant_id'] ?? null),
            'payload_json' => $entry,
            'status' => self::STATUS_PENDING,
            'attempts' => 0,
            'available_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Entries in insertion order (oldest first) — the stored payloads, not the row envelopes.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $entries = [];
        foreach ($this->table->findWhere([]) as $row) {
            $payload = $row['payload_json'] ?? null;
            if (is_array($payload)) {
                $entries[] = $payload;
            }
        }

        usort($entries, static fn (array $a, array $b): int => strcmp((string) ($a['recorded_at'] ?? ''), (string) ($b['recorded_at'] ?? '')));

        return $entries;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
