<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Rawphp\CapabilitiesAi\Contracts\ProgressStore;

/**
 * In-memory progress store for tests and single-process hosts.
 */
final class ArrayProgressStore implements ProgressStore
{
    /** @var array<string, list<array{kind: string, data?: mixed, at?: string, index: int}>> */
    private array $events = [];

    public function append(string $turnUlid, array $event): void
    {
        $kind = (string) ($event['kind'] ?? '');
        if ($kind === '') {
            throw new \InvalidArgumentException('Progress event requires kind');
        }

        $list = $this->events[$turnUlid] ?? [];
        $index = count($list);
        $list[] = [
            'kind' => $kind,
            'data' => $event['data'] ?? null,
            'at' => $event['at'] ?? gmdate('c'),
            'index' => $index,
        ];
        $this->events[$turnUlid] = $list;
    }

    public function since(string $turnUlid, int $cursor = 0): array
    {
        $list = $this->events[$turnUlid] ?? [];
        if ($cursor <= 0) {
            return $list;
        }

        return array_values(array_filter(
            $list,
            static fn (array $e): bool => $e['index'] >= $cursor
        ));
    }
}
