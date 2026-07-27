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
     * @return mixed  Eloquent Builder in production; injectable fake in unit tests
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
