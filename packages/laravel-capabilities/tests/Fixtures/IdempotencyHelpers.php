<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use DateTimeImmutable;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Idempotency\IdempotencyConfig;
use Rawphp\Capabilities\Idempotency\IdempotencyStore as DomainIdempotencyStore;
use Rawphp\Capabilities\Idempotency\RequestHash;
use Rawphp\Capabilities\Pipeline\IdempotencyGuard;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Support\SharedFakes;
use stdClass;

/**
 * Shared builders for D-005 Idempotency unit tests (no DB).
 */
final class IdempotencyHelpers
{
    public const CALLERS = ['agent', 'mcp', 'http', 'cli', 'job'];

    public const ROW_FIELDS = [
        'tenant_id',
        'actor_type',
        'actor_id',
        'capability_name',
        'idempotency_key',
        'request_hash',
        'status',
        'result_json',
        'approval_id',
        'created_at',
        'expires_at',
    ];

    public static function clock(?string $iso = null): FixedClock
    {
        return new FixedClock(new DateTimeImmutable($iso ?? '2026-01-15T12:00:00+00:00'));
    }

    public static function store(?FixedClock $clock = null, int $ttlHours = 24): DomainIdempotencyStore
    {
        $clock ??= self::clock();

        return new DomainIdempotencyStore($clock, $ttlHours);
    }

    public static function inMemoryStore(?FixedClock $clock = null): InMemoryIdempotencyStore
    {
        return new InMemoryIdempotencyStore($clock ?? self::clock());
    }

    public static function guard(
        ?object $store = null,
        ?FixedClock $clock = null,
        ?IdempotencyConfig $config = null,
    ): IdempotencyGuard {
        $clock ??= self::clock();
        $store ??= self::store($clock, $config?->ttlHours ?? 24);

        return new IdempotencyGuard(
            store: $store instanceof \Rawphp\Capabilities\Contracts\IdempotencyStore ? $store : null,
            clock: $clock,
            config: $config ?? IdempotencyConfig::defaults(),
        );
    }

    public static function mutatingDefinition(
        string $name = 'create-invoice',
        string $idempotent = CapabilityDefinition::IDEMPOTENT_OPTIONAL,
        bool $readOnly = false,
    ): CapabilityDefinition {
        return new CapabilityDefinition(
            name: $name,
            description: 'test cap',
            surfaces: ['agent', 'mcp', 'http', 'cli', 'job'],
            input: $readOnly ? null : CreateInvoiceInput::class,
            output: CreateInvoiceResult::class,
            readOnly: $readOnly,
            idempotent: $idempotent,
            run: static fn () => new CreateInvoiceResult(invoice_id: 1),
        );
    }

    public static function context(
        string $caller = 'http',
        object|string|int|null $actor = 1,
        ?string $tenantId = 'tenant-1',
    ): CapabilityContext {
        if (is_int($actor) || is_string($actor)) {
            $actor = ResolveActor::defaultUser($actor);
        } elseif ($actor === null) {
            $actor = ResolveActor::defaultUser(1);
        }

        $scope = $tenantId !== null
            ? new CapabilityScope(tenantId: $tenantId)
            : null;

        return CapabilityContext::make([
            'caller' => $caller,
            'actor' => $actor,
            'scope' => $scope,
        ]);
    }

    public static function actorFromLabel(string $label): object
    {
        // user:1 | user:2 | system:scheduler
        if (str_starts_with($label, 'system:')) {
            return SystemActor::named(substr($label, strlen('system:')));
        }
        if (str_starts_with($label, 'user:')) {
            return ResolveActor::defaultUser(substr($label, strlen('user:')));
        }

        return ResolveActor::defaultUser($label);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function hash(array $input): string
    {
        return RequestHash::of($input);
    }

    /**
     * @return array<string, mixed>
     */
    public static function inputA(): array
    {
        return [
            'customer_id' => 1,
            'amount_cents' => 100,
            'currency' => 'USD',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inputB(): array
    {
        return [
            'customer_id' => 2,
            'amount_cents' => 200,
            'currency' => 'EUR',
        ];
    }

    /**
     * Full registry harness with domain IdempotencyStore.
     *
     * @param  array<string, mixed>  $opts
     * @return array{registry: CapabilityRegistry, fakes: SharedFakes, runCount: stdClass, store: DomainIdempotencyStore, clock: FixedClock, guard: IdempotencyGuard, name: string}
     */
    public static function harness(array $opts = []): array
    {
        $clock = $opts['clock'] ?? self::clock();
        $ttl = (int) ($opts['ttl_hours'] ?? 24);
        $store = $opts['store'] ?? self::store($clock, $ttl);
        $config = $opts['config'] ?? new IdempotencyConfig(ttlHours: $ttl);
        $fakes = SharedFakes::create(clock: $clock, authorize: (bool) ($opts['authorize'] ?? true));

        // Prefer domain store for TTL semantics; keep SharedFakes for approvals/audit.
        $registry = new CapabilityRegistry(
            authorizer: $fakes->authorizer,
            approvalStore: $fakes->approvals,
            idempotencyStore: $store,
            auditWriter: $fakes->audit,
            rateLimiter: $fakes->rateLimiter,
        );

        // Replace guard with configured clock/ttl/warner.
        $guard = new IdempotencyGuard($store, $clock, $config);
        // Registry constructs its own guard; re-bind via withIdempotencyStore then we need config.
        // withIdempotencyStore creates new IdempotencyGuard without our clock — set via reflection-free path:
        // inject store then patch by constructing registry after binding isn't available.
        // Work around: use InMemory from fakes when clock is FixedClock and set expires_at in tests,
        // OR use domain store (expiry on find) which is enough for TTL tests.
        $registry = $registry->withIdempotencyStore($store);

        $runCount = new stdClass;
        $runCount->value = 0;
        $runCount->sideEffect = false;

        $name = $opts['name'] ?? 'create-invoice';
        $builder = Capability::define($name)
            ->description('idempotency test')
            ->surfaces(['agent', 'mcp', 'http', 'cli', 'job', 'artisan'])
            ->input($opts['input'] ?? CreateInvoiceInput::class)
            ->output($opts['output'] ?? CreateInvoiceResult::class)
            ->idempotent($opts['idempotent'] ?? 'optional')
            ->allowSystemCallers($opts['allowSystemCallers'] ?? true)
            ->audit(true);

        if (! empty($opts['readOnly'])) {
            // readOnly definition path: redefine without input requirement via CapabilityDefinition
        }

        if (isset($opts['approvalPolicy'])) {
            $builder->approvalPolicy($opts['approvalPolicy']);
        }

        $run = $opts['run'] ?? function ($in) use ($runCount, $opts) {
            $runCount->value++;
            $runCount->sideEffect = true;
            if (isset($opts['run_fails'])) {
                throw new \RuntimeException((string) $opts['run_fails']);
            }

            return $opts['run_output'] ?? new CreateInvoiceResult(invoice_id: 42 + $runCount->value);
        };
        $builder->run($run)->register($registry);

        if (! empty($opts['readOnly'])) {
            // Register a separate read-only capability for ignore-key tests.
            $roName = $opts['readOnlyName'] ?? 'list-invoices';
            Capability::define($roName)
                ->description('read only')
                ->surfaces(['agent', 'mcp', 'http', 'cli', 'job'])
                ->readOnly(true)
                ->idempotent($opts['idempotent'] ?? 'optional')
                ->allowSystemCallers(true)
                ->run(function () use ($runCount) {
                    $runCount->value++;

                    return ['items' => []];
                })
                ->register($registry);
        }

        return [
            'registry' => $registry,
            'fakes' => $fakes,
            'runCount' => $runCount,
            'store' => $store,
            'clock' => $clock,
            'guard' => $guard,
            'name' => $name,
            'config' => $config,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function options(string $caller = 'http', array $extra = []): array
    {
        $actor = $extra['actor'] ?? (
            $caller === 'job'
                ? SystemActor::named('billing-worker')
                : ResolveActor::defaultUser(7)
        );

        $base = [
            'caller' => $caller,
            'actor' => $actor,
            'tenant_id' => $extra['tenant_id'] ?? 'tenant-1',
        ];

        if ($caller === 'job') {
            $base['job'] = ['tenant_id' => $base['tenant_id']];
            $base['allow'] = true;
        }

        return array_merge($base, $extra);
    }

    /**
     * Seed a completed store row for direct guard/store tests.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function seedRow(object $store, array $overrides = []): array
    {
        $base = [
            'tenant_id' => 'tenant-1',
            'actor_type' => 'user',
            'actor_id' => '7',
            'capability_name' => 'create-invoice',
            'idempotency_key' => 'key-1',
            'request_hash' => self::hash(self::inputA()),
            'status' => 'completed',
            'result_json' => [
                'ok' => true,
                'data' => ['invoice_id' => 99],
                'meta' => [],
            ],
            'approval_id' => null,
            'created_at' => '2026-01-15T12:00:00+00:00',
            'expires_at' => '2026-01-16T12:00:00+00:00',
        ];

        return $store->put(array_merge($base, $overrides));
    }
}
