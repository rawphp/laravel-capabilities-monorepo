<?php

namespace Rawphp\Capabilities\Pipeline;

use Rawphp\Capabilities\Contracts\ScopeResolver;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Support\MissingJobTenantException;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Support\UnresolvedScopeException;
use RuntimeException;

/**
 * Pipeline step: resolve tenant/scope before authorize/run (D-003 / P2-005).
 *
 * SystemActor tenant comes from first-class job/context fields only —
 * never from capability wire input magic keys.
 */
final class ResolveTenantFromCaller
{
    public function __construct(
        private readonly ?ScopeResolver $scopeResolver = null,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     *                                         scope, tenant_id, require_scope, fail_scope, global_system,
     *                                         attributes, input (ignored for SystemActor scope)
     */
    public function resolve(CapabilityContext $partial, array $options = []): CapabilityScope
    {
        if (isset($options['scope']) && $options['scope'] instanceof CapabilityScope) {
            return $options['scope'];
        }

        if ($partial->scope() !== null) {
            return $partial->scope();
        }

        if (($options['fail_scope'] ?? false) === true) {
            throw new RuntimeException('Scope resolution failed (D-003).');
        }

        // Merge trusted options into context attributes for the resolver (never wire input).
        $ctx = $this->withTrustedAttrs($partial, $options);

        $resolver = $this->scopeResolver ?? new DefaultScopeResolver([
            'tenancy_required' => (bool) ($options['require_scope'] ?? $options['tenancy_required'] ?? false),
            'single_tenant_id' => $options['single_tenant_id'] ?? null,
            'memberships' => $options['memberships'] ?? [],
            'user_tenants' => $options['user_tenants'] ?? [],
        ]);

        try {
            $scope = $resolver->resolve($ctx);
        } catch (MissingJobTenantException|UnresolvedScopeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        // Fail closed on unusable scope when tenancy required.
        $require = (bool) ($options['require_scope'] ?? $options['tenancy_required'] ?? false);
        $globalSystem = (bool) ($options['global_system'] ?? $options['globalSystem'] ?? false)
            || (bool) ($ctx->contextAttr('global_system') ?? false);

        if ($require && $scope->tenantId === null && ! $globalSystem) {
            if ($ctx->actor() instanceof SystemActor) {
                throw MissingJobTenantException::forSystemActor($ctx->actor()->name);
            }
            throw UnresolvedScopeException::unusable();
        }

        return $scope;
    }

    /**
     * Documented: never promote input magic keys into SystemActor scope.
     *
     * @param  array<string, mixed>  $input
     */
    public static function systemTenantFromInputIsForbidden(array $input): bool
    {
        foreach (DefaultScopeResolver::FORBIDDEN_SYSTEM_INPUT_KEYS as $key) {
            if (array_key_exists($key, $input)) {
                // Key may be present; authority is still forbidden.
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function withTrustedAttrs(CapabilityContext $partial, array $options): CapabilityContext
    {
        $attrs = $partial->attributes();

        if (isset($options['attributes']) && is_array($options['attributes'])) {
            $attrs = array_merge($attrs, $options['attributes']);
        }

        // Trusted dispatcher fields only — never raw input.
        foreach (['tenant_id', 'team_id', 'organization_id', 'tenant_hint', 'x_tenant_id', 'cli_tenant', 'global_system', 'globalSystem', 'tenancy_required', 'require_scope', 'single_tenant_id'] as $key) {
            if (array_key_exists($key, $options) && $options[$key] !== null) {
                $attrs[$key === 'globalSystem' ? 'global_system' : $key] = $options[$key];
            }
        }

        // Explicitly do NOT copy options['input'] into attributes (P2-005).
        if ($attrs === $partial->attributes()) {
            return $partial;
        }

        return CapabilityContext::make([
            'caller' => $partial->caller(),
            'actor' => $partial->actor(),
            'scope' => $partial->scope(),
            'request_id' => $partial->requestId(),
            'trace_id' => $partial->traceId(),
            'agent' => $partial->agent(),
            'mcp' => $partial->mcp(),
            'messaging' => $partial->messaging(),
            'job' => $partial->job(),
            'credential' => $partial->credential(),
            'attributes' => $attrs,
        ]);
    }
}
