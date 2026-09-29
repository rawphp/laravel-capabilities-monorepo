<?php

namespace Rawphp\Capabilities\Support;

use Rawphp\Capabilities\Contracts\ScopedQueryFactory;

/**
 * Active tenant / team scope for an invoke (D-003).
 *
 * Resource IDs from agents/MCP/CLI/HTTP are untrusted until re-resolved under this scope.
 */
final class CapabilityScope
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly ?string $tenantId = null,
        public readonly ?string $teamId = null,
        public readonly ?string $organizationId = null,
        public readonly array $attributes = [],
        private readonly ?ScopedQueryFactory $queryFactory = null,
    ) {}

    /**
     * Start a query constrained to this scope via the app-supplied factory.
     *
     * @param  class-string  $model
     * @return mixed Eloquent Builder in production; injectable fake in unit tests
     */
    public function query(string $model): mixed
    {
        $factory = $this->queryFactory;

        if ($factory === null) {
            if (function_exists('app')) {
                try {
                    $resolved = app(ScopedQueryFactory::class);
                    if ($resolved instanceof ScopedQueryFactory) {
                        $factory = $resolved;
                    }
                } catch (\Throwable) {
                    $factory = null;
                }
            }
        }

        if ($factory === null) {
            throw new \RuntimeException(
                'CapabilityScope::query requires a ScopedQueryFactory (constructor inject or container bind).',
            );
        }

        return $factory->for($this, $model);
    }

    /**
     * JSON-safe row shape for the approval row's `scope` column (D-006, L-501): tenant, team,
     * organization and scalar attributes. The query factory (a closure) and non-scalar
     * attributes are not persisted; {@see fromRow()} rebuilds the scope at execution.
     *
     * @return array{tenant_id: ?string, team_id: ?string, organization_id: ?string, attributes: array<string, scalar|null>}
     */
    public function toRow(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'team_id' => $this->teamId,
            'organization_id' => $this->organizationId,
            'attributes' => array_filter($this->attributes, static fn (mixed $v): bool => $v === null || is_scalar($v)),
        ];
    }

    /**
     * Rebuild the scope an approval request was stamped with, so accept re-check and
     * execution see what the approver saw (D-006, L-501). The row's `tenant_id` column —
     * the tenant the approver was placed in — is the tenant; team, organization and
     * attributes come from the `scope` array written by {@see toRow()}. The tenant may be
     * null: an untenanted row (team-only host, global system work) still runs under its
     * stamped team / organization (L-601). A legacy string / null `scope` yields a
     * tenant-only scope; a legacy row with neither tenant nor stamp yields null so scope
     * resolves at execution time. A rebuilt scope has no query factory: `query()` uses the
     * container-bound {@see ScopedQueryFactory}, the same route as any resolver scope.
     *
     * @param  array<mixed>  $row  approval store row
     */
    public static function fromRow(array $row): ?self
    {
        $tenant = is_scalar($row['tenant_id'] ?? null) && (string) $row['tenant_id'] !== '' ? (string) $row['tenant_id'] : null;
        $stamped = is_array($row['scope'] ?? null) ? $row['scope'] : null;
        if ($tenant === null && $stamped === null) {
            return null;
        }

        $stamped ??= [];
        $str = static fn (string $key): ?string => is_scalar($stamped[$key] ?? null) ? (string) $stamped[$key] : null;
        $attributes = is_array($stamped['attributes'] ?? null) ? $stamped['attributes'] : [];

        return new self(
            tenantId: $tenant,
            teamId: $str('team_id'),
            organizationId: $str('organization_id'),
            attributes: array_filter($attributes, static fn (mixed $v): bool => $v === null || is_scalar($v)),
        );
    }

    public function withQueryFactory(ScopedQueryFactory $factory): self
    {
        return new self(
            tenantId: $this->tenantId,
            teamId: $this->teamId,
            organizationId: $this->organizationId,
            attributes: $this->attributes,
            queryFactory: $factory,
        );
    }
}
