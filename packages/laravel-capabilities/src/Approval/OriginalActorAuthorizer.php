<?php

namespace Rawphp\Capabilities\Approval;

use Closure;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\SystemActor;

/**
 * Original-actor re-check on accept (spec: re-validation on accept, step 4).
 *
 * Rehydrates the requester recorded on the approval row and re-runs the registry's
 * authorize decision with the stored input under the row's tenant. Fails closed:
 * an unknown capability, unresolvable requester, or unknown caller denies.
 *
 * System requesters rehydrate as {@see SystemActor}; user requesters go through the
 * host resolver (the service provider defaults to the auth guard's user provider).
 */
final class OriginalActorAuthorizer
{
    /**
     * @param  Closure(string, string): ?object  $resolveUser  (requester type, requester id) => actor or null
     */
    public function __construct(
        private readonly CapabilityRegistry $registry,
        private readonly Closure $resolveUser,
    ) {}

    /**
     * @param  array<mixed>  $row  approval row
     */
    public function __invoke(array $row): bool
    {
        $caller = self::field($row, 'original_caller');
        $input = $row['input_json'] ?? null;
        if (! in_array($caller, CapabilityContext::CALLERS, true) || ! is_array($input)) {
            return false;
        }

        $actor = $this->actor(self::field($row, 'requester_actor_type'), self::field($row, 'requester_actor_id'));
        if ($actor === null) {
            return false;
        }

        $tenant = self::field($row, 'tenant_id');
        $context = new CapabilityContext(
            caller: $caller,
            actor: $actor,
            scope: $tenant === '' ? null : new CapabilityScope(tenantId: $tenant),
        );

        /** @var array<string, mixed> $input */
        return $this->registry->authorizes(self::field($row, 'capability_name'), $input, $context);
    }

    private function actor(string $type, string $id): ?object
    {
        if ($id === '') {
            return null;
        }

        if ($type === 'system') {
            return new SystemActor($id);
        }

        return ($this->resolveUser)($type, $id);
    }

    /**
     * @param  array<mixed>  $row
     */
    private static function field(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }
}
