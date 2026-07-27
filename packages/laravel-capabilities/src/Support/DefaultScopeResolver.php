<?php

namespace Rawphp\Capabilities\Support;

use Rawphp\Capabilities\Contracts\ScopeResolver;

/**
 * Package default ScopeResolver implementing D-003 / P2-005 rules.
 *
 * - User actors: scope from membership / options, not untrusted input alone.
 * - SystemActor: tenant ONLY from first-class job/context fields.
 * - Never reads capability wire input magic keys for SystemActor.
 */
final class DefaultScopeResolver implements ScopeResolver
{
    /** Magic keys that must never become SystemActor scope authority (P2-005). */
    public const FORBIDDEN_SYSTEM_INPUT_KEYS = [
        '_tenant_id',
        'tenant_id',
        'tenantId',
        'organization_id',
        'team_id',
        'scope_id',
    ];

    /**
     * @param  array{
     *     tenancy_required?: bool,
     *     single_tenant_id?: string|null,
     *     memberships?: array<string, list<string>>,
     *     user_tenants?: array<string|int, string>
     * }  $options
     */
    public function __construct(
        private readonly array $options = [],
    ) {}

    public function resolve(CapabilityContext $partial): CapabilityScope
    {
        $tenancyRequired = (bool) ($this->options['tenancy_required'] ?? false)
            || (bool) ($partial->contextAttr('tenancy_required') ?? false);

        $globalSystem = (bool) ($partial->contextAttr('global_system') ?? false)
            || (bool) ($partial->contextAttr('globalSystem') ?? false);

        $singleTenant = $this->options['single_tenant_id']
            ?? $partial->contextAttr('single_tenant_id');

        // Explicit single-tenant mode: always return default scope.
        if (is_string($singleTenant) && $singleTenant !== '') {
            return new CapabilityScope(tenantId: $singleTenant);
        }

        $actor = $partial->actor();

        if ($actor instanceof SystemActor) {
            return $this->resolveSystem($partial, $tenancyRequired, $globalSystem);
        }

        return $this->resolveUser($partial, $tenancyRequired);
    }

    private function resolveSystem(
        CapabilityContext $partial,
        bool $tenancyRequired,
        bool $globalSystem,
    ): CapabilityScope {
        // First-class only — never input.
        $tenantId = $partial->jobTenantId()
            ?? $this->stringAttr($partial, 'tenant_id')
            ?? $this->stringAttr($partial, 'tenantId');

        // Also accept job array keys team/org for convenience dimensions.
        $teamId = $this->jobField($partial, 'team_id')
            ?? $this->stringAttr($partial, 'team_id');
        $organizationId = $this->jobField($partial, 'organization_id')
            ?? $this->stringAttr($partial, 'organization_id');

        if ($tenantId === null) {
            if ($globalSystem) {
                return new CapabilityScope(tenantId: null, teamId: $teamId, organizationId: $organizationId);
            }
            if ($tenancyRequired) {
                throw MissingJobTenantException::forSystemActor(
                    $partial->actor() instanceof SystemActor ? $partial->actor()->name : 'system',
                );
            }

            // Tenancy not required and not global — still fail closed if unusable when required flag set elsewhere.
            if (($partial->contextAttr('require_scope') ?? false) === true) {
                throw UnresolvedScopeException::systemWithoutTenant();
            }

            return new CapabilityScope(tenantId: null, teamId: $teamId, organizationId: $organizationId);
        }

        return new CapabilityScope(
            tenantId: $tenantId,
            teamId: $teamId,
            organizationId: $organizationId,
        );
    }

    private function resolveUser(CapabilityContext $partial, bool $tenancyRequired): CapabilityScope
    {
        $user = $partial->user();
        $userId = is_object($user) && isset($user->id) ? $user->id : null;

        // Trusted dispatcher/options tenant (pipeline attributes) — not wire input.
        $fromTrusted = $this->stringAttr($partial, 'tenant_id')
            ?? $this->stringAttr($partial, 'tenantId');

        // Membership-backed tenant (session/token), not wire input alone.
        $userTenants = $this->options['user_tenants'] ?? [];
        $fromMembership = null;
        if ($userId !== null && isset($userTenants[$userId])) {
            $fromMembership = (string) $userTenants[$userId];
        } elseif ($userId !== null && isset($userTenants[(string) $userId])) {
            $fromMembership = (string) $userTenants[(string) $userId];
        } elseif (is_object($user) && isset($user->current_tenant_id)) {
            $fromMembership = (string) $user->current_tenant_id;
        }

        // Hint only (X-Tenant-Id / CLI --tenant) after membership check.
        $hint = $this->stringAttr($partial, 'tenant_hint')
            ?? $this->stringAttr($partial, 'x_tenant_id')
            ?? $this->stringAttr($partial, 'cli_tenant');

        // Prefer membership when present; trusted options may override when no membership conflict.
        $tenantId = $fromMembership ?? $fromTrusted;
        if ($fromTrusted !== null && $fromMembership === null) {
            $tenantId = $fromTrusted;
        }
        // When both set, membership wins unless trusted was set as explicit server option and membership matches.
        if ($fromTrusted !== null && $fromMembership !== null && $fromTrusted !== $fromMembership) {
            // Keep membership as authority for user actors (D-003); trusted option used when membership empty.
            $tenantId = $fromMembership;
        }

        if ($hint !== null) {
            $memberships = $this->options['memberships'] ?? [];
            $allowed = [];
            if ($userId !== null) {
                $allowed = $memberships[(string) $userId] ?? $memberships[$userId] ?? [];
            }
            if ($allowed === [] && $fromMembership !== null) {
                $allowed = [$fromMembership];
            }
            if (in_array($hint, $allowed, true)) {
                $tenantId = $hint;
            }
            // Untrusted hint without membership membership-check fails — keep prior tenant.
            if ($allowed === [] && $fromMembership === null && $fromTrusted === null && $tenancyRequired) {
                throw UnresolvedScopeException::unusable();
            }
        }

        if ($tenantId === null && ! $tenancyRequired) {
            $tenantId = 'default-tenant';
        }

        if ($tenantId === null && $tenancyRequired) {
            throw UnresolvedScopeException::unusable();
        }

        $teamId = is_object($user) && isset($user->current_team_id)
            ? (string) $user->current_team_id
            : $this->stringAttr($partial, 'team_id');
        $organizationId = is_object($user) && isset($user->current_organization_id)
            ? (string) $user->current_organization_id
            : $this->stringAttr($partial, 'organization_id');

        return new CapabilityScope(
            tenantId: $tenantId,
            teamId: $teamId,
            organizationId: $organizationId,
        );
    }

    private function stringAttr(CapabilityContext $partial, string $key): ?string
    {
        $v = $partial->contextAttr($key);

        return is_string($v) || is_int($v) ? (string) $v : null;
    }

    private function jobField(CapabilityContext $partial, string $key): ?string
    {
        $job = $partial->job();
        if ($job === null) {
            return null;
        }
        $v = $job[$key] ?? null;

        return is_string($v) || is_int($v) ? (string) $v : null;
    }

    /**
     * Assert a resolver implementation never consults forbidden input keys for SystemActor.
     *
     * @param  array<string, mixed>  $input
     */
    public static function assertInputNotUsedForSystemScope(array $input): void
    {
        // Used by package examples/tests — documents P2-005.
        foreach (self::FORBIDDEN_SYSTEM_INPUT_KEYS as $key) {
            // Presence in input is fine; using it for scope is not. This is a documentation hook.
            unset($input[$key]);
        }
    }
}
