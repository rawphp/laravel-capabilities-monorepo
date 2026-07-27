<?php

namespace Rawphp\Capabilities\Persistence;

/**
 * Minimal row gateway so database stores stay unit-testable without a live DB.
 *
 * Production: {@see QueryTableGateway} (Illuminate query builder / connection).
 * Tests: {@see ArrayTableGateway} (in-process, no connection).
 */
interface TableGateway
{
    /**
     * Insert a row keyed by primary string id (or generated).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function insert(array $row): array;

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array;

    /**
     * Unconditional replace of known id.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null  null when id missing
     */
    public function replace(string $id, array $row): ?array;

    /**
     * Conditional update: only when all $where equalities match.
     *
     * @param  array<string, mixed>  $where
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null  null when no row matched
     */
    public function updateWhere(array $where, array $attributes): ?array;

    /**
     * @param  array<string, mixed>  $where
     * @return list<array<string, mixed>>
     */
    public function findWhere(array $where): array;

    /**
     * Upsert by composite unique key map (for idempotency identity).
     *
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function upsert(array $identity, array $row): array;
}
