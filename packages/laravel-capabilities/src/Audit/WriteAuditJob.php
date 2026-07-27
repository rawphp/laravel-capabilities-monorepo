<?php

namespace Rawphp\Capabilities\Audit;

use Rawphp\Capabilities\Contracts\AuditWriter;
use Throwable;

/**
 * Drains audit outbox rows into the durable {@see AuditWriter} (D-010).
 *
 * At-least-once: consumers must tolerate duplicates via invocation identity.
 */
final class WriteAuditJob
{
    public function __construct(
        private readonly AuditOutbox $outbox,
        private readonly AuditWriter $writer,
    ) {}

    /**
     * Process pending outbox rows. Returns number of successfully written entries.
     */
    public function handle(?int $limit = null): int
    {
        $written = 0;
        $pending = $this->outbox->pending();
        if ($limit !== null) {
            $pending = array_slice($pending, 0, max(0, $limit));
        }

        foreach ($pending as $row) {
            $id = (string) $row['id'];
            $this->outbox->markProcessing($id);
            try {
                /** @var array<string, mixed> $entry */
                $entry = $row['entry'];
                $this->writer->write($entry);
                $this->outbox->markCompleted($id);
                $written++;
            } catch (Throwable $e) {
                $this->outbox->markFailed($id, $e->getMessage());
            }
        }

        return $written;
    }
}
