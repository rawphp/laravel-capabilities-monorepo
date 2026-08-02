<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Contracts;

/**
 * Turn progress event stream (not MySQL product DB).
 *
 * Event kinds: status | token | tool | error | terminal
 */
interface ProgressStore
{
    /**
     * @param  array{kind: string, data?: mixed, at?: string}  $event
     */
    public function append(string $turnUlid, array $event): void;

    /**
     * Events strictly after $cursor (cursor is 0-based exclusive end index).
     * Empty cursor / 0 returns from the start.
     *
     * @return list<array{kind: string, data?: mixed, at?: string, index: int}>
     */
    public function since(string $turnUlid, int $cursor = 0): array;
}
