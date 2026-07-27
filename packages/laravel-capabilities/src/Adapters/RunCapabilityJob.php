<?php

namespace Rawphp\Capabilities\Adapters;

use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\MissingJobActorException;
use Rawphp\Capabilities\Support\MissingJobTenantException;
use Rawphp\Capabilities\Support\SystemActor;
use stdClass;

/**
 * Queue / scheduler invoke surface — requires explicit actor (D-002 / P2-005).
 *
 * Unit tests call {@see dispatchSync()} without a real queue worker.
 * Production may wrap this in a Laravel Job that calls the same prepare/run path.
 */
final class RunCapabilityJob
{
    /**
     * @param  array<string, mixed>  $input
     * @param  int|string|SystemActor|null  $actingAs
     * @param  array<string, mixed>  $meta  teamId, organizationId, idempotencyKey, user_resolver, etc.
     */
    public function __construct(
        public readonly string $name,
        public readonly array $input = [],
        public readonly int|string|SystemActor|null $actingAs = null,
        public readonly ?string $tenantId = null,
        public readonly ?string $teamId = null,
        public readonly ?string $organizationId = null,
        public readonly ?string $idempotencyKey = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     input?: array<string, mixed>,
     *     actingAs?: int|string|SystemActor|null,
     *     tenantId?: string|null,
     *     teamId?: string|null,
     *     organizationId?: string|null,
     *     idempotencyKey?: string|null
     * }  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            name: (string) $payload['name'],
            input: $payload['input'] ?? [],
            actingAs: $payload['actingAs'] ?? null,
            tenantId: isset($payload['tenantId']) ? (string) $payload['tenantId'] : null,
            teamId: isset($payload['teamId']) ? (string) $payload['teamId'] : null,
            organizationId: isset($payload['organizationId']) ? (string) $payload['organizationId'] : null,
            idempotencyKey: isset($payload['idempotencyKey']) ? (string) $payload['idempotencyKey'] : null,
        );
    }

    /**
     * Validate payload before enqueue — never enqueued as null-user (D-002).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function assertDispatchable(array $payload): void
    {
        if (! array_key_exists('actingAs', $payload) || $payload['actingAs'] === null) {
            throw MissingJobActorException::missing();
        }
    }

    /**
     * @param  array{
     *     name: string,
     *     input?: array<string, mixed>,
     *     actingAs?: int|string|SystemActor|null,
     *     tenantId?: string|null,
     *     teamId?: string|null,
     *     organizationId?: string|null,
     *     idempotencyKey?: string|null,
     *     tenancy_required?: bool,
     *     globalSystem?: bool
     * }  $payload
     */
    public static function dispatch(array $payload): self
    {
        self::assertDispatchable($payload);

        return self::fromPayload($payload);
    }

    /**
     * @param  array{
     *     name: string,
     *     input?: array<string, mixed>,
     *     actingAs?: int|string|SystemActor|null,
     *     tenantId?: string|null,
     *     teamId?: string|null,
     *     organizationId?: string|null,
     *     idempotencyKey?: string|null,
     *     tenancy_required?: bool,
     *     globalSystem?: bool,
     *     user_resolver?: callable|null
     * }  $payload
     */
    public static function dispatchSync(CapabilityRegistry $registry, array $payload): CapabilityResult
    {
        self::assertDispatchable($payload);
        $job = self::fromPayload($payload);

        return $job->handle($registry, $payload);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function handle(CapabilityRegistry $registry, array $options = []): CapabilityResult
    {
        if ($this->actingAs === null) {
            throw MissingJobActorException::missing();
        }

        $actor = $this->resolveActor($options);
        $definition = $registry->has($this->name) ? $registry->get($this->name) : null;

        $globalSystem = (bool) ($options['globalSystem'] ?? $definition?->globalSystem ?? false);
        $tenancyRequired = (bool) ($options['tenancy_required'] ?? true);

        if ($actor instanceof SystemActor) {
            if ($definition !== null && ! $definition->allowsSystemCaller($actor)) {
                return CapabilityResult::failure(
                    code: 'forbidden',
                    message: sprintf('SystemActor "%s" is not allowed for capability "%s".', $actor->name, $this->name),
                );
            }

            if ($this->tenantId === null && $tenancyRequired && ! $globalSystem) {
                throw MissingJobTenantException::forSystemActor($actor->name);
            }
        }

        $jobMeta = array_filter([
            'tenant_id' => $this->tenantId,
            'team_id' => $this->teamId,
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'acting_as' => $actor instanceof SystemActor ? $actor->name : (string) ($actor->id ?? ''),
        ], fn ($v) => $v !== null && $v !== '');

        $invokeOptions = [
            'caller' => 'job',
            'actor' => $actor,
            'job' => $jobMeta,
            'tenant_id' => $this->tenantId,
            'require_scope' => $tenancyRequired && ! $globalSystem,
            'global_system' => $globalSystem,
            'attributes' => array_filter([
                'tenant_id' => $this->tenantId,
                'team_id' => $this->teamId,
                'organization_id' => $this->organizationId,
                'global_system' => $globalSystem,
                'tenancy_required' => $tenancyRequired,
                'require_scope' => $tenancyRequired && ! $globalSystem,
            ], fn ($v) => $v !== null),
            'idempotency_key' => $this->idempotencyKey,
        ];

        if (isset($options['scope_resolver'])) {
            $invokeOptions['scope_resolver'] = $options['scope_resolver'];
        }

        return $registry->invoke($this->name, $this->input, $invokeOptions);
    }

    /**
     * Tags for failed-job hooks (D-019) — pure data, no Laravel queue required.
     *
     * @return array{capability: string, caller: string, actor_type: string, tenant_id: ?string}
     */
    public function failureTags(): array
    {
        $actorType = 'unknown';
        if ($this->actingAs instanceof SystemActor) {
            $actorType = 'system';
        } elseif ($this->actingAs !== null) {
            $actorType = 'user';
        }

        return [
            'capability' => $this->name,
            'caller' => 'job',
            'actor_type' => $actorType,
            'tenant_id' => $this->tenantId,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveActor(array $options): object
    {
        if ($this->actingAs instanceof SystemActor) {
            return $this->actingAs;
        }

        if (is_int($this->actingAs) || is_string($this->actingAs)) {
            $resolver = $options['user_resolver'] ?? null;
            if (is_callable($resolver)) {
                $user = $resolver($this->actingAs);
                if ($user === null) {
                    throw new \RuntimeException(sprintf('User id "%s" not found for job actingAs (D-002).', (string) $this->actingAs));
                }

                return $user;
            }

            $user = new stdClass;
            $user->id = $this->actingAs;
            $user->name = 'job-user-'.$this->actingAs;
            if ($this->tenantId !== null) {
                $user->current_tenant_id = $this->tenantId;
            }

            return $user;
        }

        throw MissingJobActorException::missing();
    }
}
