<?php

namespace Rawphp\Capabilities\Support;

use Rawphp\Capabilities\Contracts\ScopedQueryFactory;

/**
 * In-memory scoped resource map for unit tests (D-003) — no Eloquent/DB.
 *
 * Resources are keyed by model class + id; query()->find() returns null when
 * the resource's tenant does not match the active CapabilityScope.
 */
final class InMemoryScopedQueryFactory implements ScopedQueryFactory
{
    /**
     * @param  array<string, array<string|int, array{tenant_id: string, data?: array<string, mixed>}>>  $resources
     *         model class => [ id => ['tenant_id' => ..., 'data' => ...] ]
     */
    public function __construct(
        private array $resources = [],
    ) {}

    /**
     * @param  array<string|int, array{tenant_id: string, data?: array<string, mixed>}>  $rows
     */
    public function put(string $model, array $rows): void
    {
        $this->resources[$model] = ($this->resources[$model] ?? []) + $rows;
    }

    public function for(CapabilityScope $scope, string $model): InMemoryScopedQuery
    {
        return new InMemoryScopedQuery(
            scope: $scope,
            rows: $this->resources[$model] ?? [],
        );
    }
}

/**
 * Fake builder returned by {@see InMemoryScopedQueryFactory::for()}.
 */
final class InMemoryScopedQuery
{
    /**
     * @param  array<string|int, array{tenant_id: string, data?: array<string, mixed>}>  $rows
     */
    public function __construct(
        private readonly CapabilityScope $scope,
        private readonly array $rows,
    ) {}

    /**
     * Re-resolve a resource id under the active tenant scope (D-003).
     *
     * @return array<string, mixed>|null
     */
    public function find(string|int $id): ?array
    {
        $row = $this->rows[$id] ?? $this->rows[(string) $id] ?? null;
        if ($row === null) {
            return null;
        }

        $tenant = $this->scope->tenantId;
        if ($tenant !== null && (string) $row['tenant_id'] !== (string) $tenant) {
            return null;
        }

        return array_merge(
            ['id' => $id, 'tenant_id' => $row['tenant_id']],
            $row['data'] ?? [],
        );
    }

    public function first(): ?array
    {
        foreach (array_keys($this->rows) as $id) {
            $found = $this->find($id);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
