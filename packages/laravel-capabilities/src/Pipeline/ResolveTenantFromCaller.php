<?php

namespace Rawphp\Capabilities\Pipeline;

use Rawphp\Capabilities\Contracts\ScopeResolver;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\SystemActor;
use RuntimeException;

/**
 * Pipeline step: resolve tenant/scope before authorize/run (D-003).
 */
final class ResolveTenantFromCaller
{
    public function __construct(
        private readonly ?ScopeResolver $scopeResolver = null,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function resolve(CapabilityContext $partial, array $options = []): CapabilityScope
    {
        if (isset($options['scope']) && $options['scope'] instanceof CapabilityScope) {
            return $options['scope'];
        }

        if ($partial->scope() !== null) {
            return $partial->scope();
        }

        if ($this->scopeResolver !== null) {
            return $this->scopeResolver->resolve($partial);
        }

        if (($options['fail_scope'] ?? false) === true) {
            throw new RuntimeException('Scope resolution failed (D-003).');
        }

        // SystemActor: tenant only from first-class job/context fields (P2-005).
        if ($partial->actor() instanceof SystemActor) {
            $tenant = $partial->jobTenantId()
                ?? (isset($options['tenant_id']) ? (string) $options['tenant_id'] : null);

            if ($tenant === null && ($options['require_scope'] ?? false) === true) {
                throw new RuntimeException('Unable to resolve scope for SystemActor without tenant (D-003).');
            }

            return new CapabilityScope(tenantId: $tenant);
        }

        $tenant = isset($options['tenant_id']) ? (string) $options['tenant_id'] : 'default-tenant';

        return new CapabilityScope(tenantId: $tenant);
    }
}
