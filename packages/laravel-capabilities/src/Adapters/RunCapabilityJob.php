<?php

namespace Rawphp\Capabilities\Adapters;

use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use LogicException;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\MissingJobActorException;
use Rawphp\Capabilities\Support\MissingJobTenantException;
use Rawphp\Capabilities\Support\SystemActor;
use Throwable;

/**
 * Queue / scheduler invoke surface — a real queueable job (D-002 / P2-005 / L-016).
 *
 * `RunCapabilityJob::dispatch($payload)` validates the actor and pushes the job onto the
 * bus; a worker then calls {@see handle()} with the container's {@see CapabilityRegistry}.
 * User ids resolve through the registry's requester resolver (the host auth provider the
 * service provider wires — the same lookup approvals use); unresolvable → fail closed.
 * {@see make()} builds without enqueuing; {@see dispatchSync()} runs inline (unit tests).
 */
final class RunCapabilityJob implements ShouldQueue
{
    use Queueable;

    /** @var array<string, mixed>|null */
    private ?array $lastFailure = null;

    /**
     * @param  array<string, mixed>  $input
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
     * Build the validated job without enqueuing it (never a null-user job — D-002).
     *
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
    public static function make(array $payload): self
    {
        self::assertDispatchable($payload);

        return self::fromPayload($payload);
    }

    /**
     * Validate, build and push onto the bus. Uses $bus when given, otherwise the container's
     * {@see Dispatcher}; with neither this fails closed instead of silently building an object.
     *
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
    public static function dispatch(array $payload, ?Dispatcher $bus = null): self
    {
        $job = self::make($payload);

        if ($bus === null) {
            $container = Container::getInstance();
            if (! $container->bound(Dispatcher::class)) {
                throw new LogicException(
                    'RunCapabilityJob::dispatch() needs an Illuminate\\Contracts\\Bus\\Dispatcher: pass one, or dispatch inside a booted Laravel app.'
                );
            }
            $bus = $container->make(Dispatcher::class);
        }

        $bus->dispatch($job);

        return $job;
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
     * Queue worker entry (container injects the registry singleton). $options is the
     * inline / unit path (`user_resolver`, `scope_resolver`, tenancy overrides).
     *
     * @param  array<string, mixed>  $options
     */
    public function handle(CapabilityRegistry $registry, array $options = []): CapabilityResult
    {
        if ($this->actingAs === null) {
            throw MissingJobActorException::missing();
        }

        $actor = $this->resolveActor($registry, $options);
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
     * Laravel failed-job hook (D-019): keep the tags for the failed_jobs consumer and log
     * them through the app logger when one is bound. Never throws.
     */
    public function failed(?Throwable $exception = null): void
    {
        $this->lastFailure = $this->failureTags() + [
            'exception' => $exception === null ? null : $exception::class,
            'message' => $exception?->getMessage(),
        ];

        try {
            $container = Container::getInstance();
            if ($container->bound('log')) {
                $logger = $container->make('log');
                if (is_object($logger) && method_exists($logger, 'error')) {
                    $logger->error('capability.job.failed', $this->lastFailure);
                }
            }
        } catch (Throwable) {
            // logging is best effort; the failed_jobs row still carries the payload
        }
    }

    /**
     * @return array<string, mixed>|null tags + exception recorded by {@see failed()}
     */
    public function lastFailure(): ?array
    {
        return $this->lastFailure;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveActor(CapabilityRegistry $registry, array $options): object
    {
        if ($this->actingAs instanceof SystemActor) {
            return $this->actingAs;
        }

        if (is_int($this->actingAs) || is_string($this->actingAs)) {
            $resolver = $options['user_resolver'] ?? null;
            if (! is_callable($resolver)) {
                if (! $registry->hasRequesterResolver()) {
                    throw MissingJobActorException::unresolvableUser($this->actingAs);
                }
                $resolver = static fn (int|string $id): ?object => $registry->resolveRequester('user', (string) $id);
            }

            $user = $resolver($this->actingAs);
            if ($user === null) {
                throw new \RuntimeException(sprintf('User id "%s" not found for job actingAs (D-002).', (string) $this->actingAs));
            }

            return $user;
        }

        throw MissingJobActorException::missing();
    }
}
