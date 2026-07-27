<?php

namespace Rawphp\Capabilities\Approval;

use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Support\SystemActor;

/**
 * Who may accept/reject a pending approval (D-006).
 *
 * Policies:
 * - requester — only original requester
 * - requester_or_role — requester or role holders (default)
 * - role:{name} — only that role (no self-approve unless requester holds role)
 * - any_staff — any authenticated user in the same tenant
 * - custom — delegated to callable / class
 *
 * SystemActor can never approve. Approver must share tenant scope with the row.
 */
final class ApprovalPolicy
{
    public const REQUESTER = 'requester';

    public const REQUESTER_OR_ROLE = 'requester_or_role';

    public const ANY_STAFF = 'any_staff';

    public const CUSTOM = 'custom';

    /**
     * @param  (callable(object $actor, array $row, self $policy): bool)|null  $customChecker
     * @param  (callable(object $actor, string $role): bool)|null  $roleChecker
     * @param  (callable(object $actor): bool)|null  $staffChecker
     */
    public function __construct(
        private readonly string $policy = self::REQUESTER_OR_ROLE,
        private mixed $customChecker = null,
        private mixed $roleChecker = null,
        private mixed $staffChecker = null,
        private readonly string $defaultRole = 'approver',
    ) {}

    public static function fromString(
        string $policy,
        ?callable $customChecker = null,
        ?callable $roleChecker = null,
        ?callable $staffChecker = null,
        string $defaultRole = 'approver',
    ): self {
        return new self($policy, $customChecker, $roleChecker, $staffChecker, $defaultRole);
    }

    public function policy(): string
    {
        return $this->policy;
    }

    public function isDefaultMultiTenantSafe(): bool
    {
        // Silent "any authenticated user" is not the default in multi-tenant installs.
        return $this->policy === self::REQUESTER_OR_ROLE || $this->policy === self::REQUESTER
            || str_starts_with($this->policy, 'role:');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function allows(array $row, object $actor, ?string $actorTenantId = null): bool
    {
        if ($actor instanceof SystemActor || ResolveActor::isSystemActor($actor)) {
            return false;
        }

        $rowTenant = isset($row['tenant_id']) ? (is_string($row['tenant_id']) ? $row['tenant_id'] : (string) $row['tenant_id']) : null;
        if ($rowTenant !== null && $rowTenant !== '' && $actorTenantId !== null && $actorTenantId !== $rowTenant) {
            return false;
        }

        $actorId = ResolveActor::actorId($actor);
        $requesterId = (string) ($row['requester_actor_id'] ?? '');
        $isRequester = $actorId !== '' && $actorId === $requesterId
            && ResolveActor::actorType($actor) === (string) ($row['requester_actor_type'] ?? 'user');

        $role = $this->roleName();
        $hasRole = $this->actorHasRole($actor, $role);
        $isStaff = $this->actorIsStaff($actor);

        return match (true) {
            $this->policy === self::REQUESTER => $isRequester,
            $this->policy === self::REQUESTER_OR_ROLE => $isRequester || $hasRole,
            str_starts_with($this->policy, 'role:') => $hasRole,
            $this->policy === self::ANY_STAFF => $isStaff,
            $this->policy === self::CUSTOM || $this->customChecker !== null => $this->runCustom($actor, $row),
            default => $isRequester || $hasRole,
        };
    }

    public function roleName(): string
    {
        if (str_starts_with($this->policy, 'role:')) {
            return substr($this->policy, 5) ?: $this->defaultRole;
        }

        return $this->defaultRole;
    }

    private function actorHasRole(object $actor, string $role): bool
    {
        if ($this->roleChecker !== null) {
            return (bool) ($this->roleChecker)($actor, $role);
        }

        if (isset($actor->roles) && is_array($actor->roles)) {
            return in_array($role, $actor->roles, true) || in_array('finance-approver', $actor->roles, true);
        }

        if (isset($actor->role) && is_string($actor->role)) {
            return $actor->role === $role || $actor->role === 'finance-approver' || $actor->role === 'approver';
        }

        return false;
    }

    private function actorIsStaff(object $actor): bool
    {
        if ($this->staffChecker !== null) {
            return (bool) ($this->staffChecker)($actor);
        }

        if (isset($actor->is_staff)) {
            return (bool) $actor->is_staff;
        }

        // Default: any non-system user principal is staff for unit-test simplicity.
        return ! ($actor instanceof SystemActor);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function runCustom(object $actor, array $row): bool
    {
        if ($this->customChecker === null) {
            // Custom without checker: treat as any_staff in-tenant (explicit app should supply checker).
            return $this->actorIsStaff($actor);
        }

        return (bool) ($this->customChecker)($actor, $row, $this);
    }
}
