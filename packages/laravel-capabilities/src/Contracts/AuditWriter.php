<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Writes structured audit entries for capability lifecycle events (D-010).
 *
 * Failures are handled by the audit mode (best_effort vs strict) higher in the
 * pipeline — this contract only defines the write surface.
 *
 * @phpstan-type AuditEntry array{
 *     event: string,
 *     capability_name?: string|null,
 *     tenant_id?: string|null,
 *     actor_type?: string|null,
 *     actor_id?: string|null,
 *     payload?: mixed,
 *     recorded_at?: string
 * }
 */
interface AuditWriter
{
    /**
     * @param  array<string, mixed>  $entry
     */
    public function write(array $entry): void;

    /**
     * All entries written so far (in-memory fakes; production may throw).
     *
     * @return list<AuditEntry>
     */
    public function all(): array;
}
