<?php

namespace Rawphp\Capabilities\Audit;

use InvalidArgumentException;
use Rawphp\Capabilities\Contracts\Clock;
use Rawphp\Capabilities\Support\SystemClock;

/**
 * Best-effort audit outbox (D-010) — durable intent for at-least-once writes.
 *
 * Unit tests inject this in-memory; production may bind a DB-backed driver.
 */
final class AuditOutbox
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    private int $seq = 0;

    public function __construct(
        private readonly Clock $clock = new SystemClock,
    ) {}

    /**
     * @param  array<string, mixed>  $entry
     */
    public function enqueue(array $entry): string
    {
        $this->seq++;
        $id = 'outbox-'.$this->seq;
        $this->rows[$id] = [
            'id' => $id,
            'status' => self::STATUS_PENDING,
            'entry' => $entry,
            'attempts' => 0,
            'error' => null,
            'created_at' => $this->clock->now()->format(DATE_ATOM),
            'updated_at' => $this->clock->now()->format(DATE_ATOM),
        ];

        return $id;
    }

    public function markProcessing(string $id): void
    {
        $row = $this->require($id);
        $row['status'] = self::STATUS_PROCESSING;
        $row['attempts'] = ((int) $row['attempts']) + 1;
        $row['updated_at'] = $this->clock->now()->format(DATE_ATOM);
        $this->rows[$id] = $row;
    }

    public function markCompleted(string $id): void
    {
        $row = $this->require($id);
        $row['status'] = self::STATUS_COMPLETED;
        $row['error'] = null;
        $row['updated_at'] = $this->clock->now()->format(DATE_ATOM);
        $this->rows[$id] = $row;
    }

    public function markFailed(string $id, string $reason): void
    {
        $row = $this->require($id);
        $row['status'] = self::STATUS_FAILED;
        $row['error'] = $reason;
        $row['updated_at'] = $this->clock->now()->format(DATE_ATOM);
        $this->rows[$id] = $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pending(): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (array $r): bool => $r['status'] === self::STATUS_PENDING,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return array_values($this->rows);
    }

    public function find(string $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function countByStatus(string $status): int
    {
        return count(array_filter(
            $this->rows,
            static fn (array $r): bool => $r['status'] === $status,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function require(string $id): array
    {
        if (! isset($this->rows[$id])) {
            throw new InvalidArgumentException(sprintf('Unknown outbox row "%s".', $id));
        }

        return $this->rows[$id];
    }
}
