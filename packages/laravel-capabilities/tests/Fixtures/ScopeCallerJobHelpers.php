<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Http\CallerDeriver;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Support\InMemoryScopedQueryFactory;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Support\SharedFakes;
use stdClass;

/**
 * Shared builders for Scope / Caller / Job / Context unit tests (REQ-006).
 */
final class ScopeCallerJobHelpers
{
    public const CALLERS = ['agent', 'mcp', 'http', 'cli', 'job'];

    public const ATTACKS = [
        'customer_id_other_tenant',
        'invoice_id_other_tenant',
        'team_id_spoof_header',
        'tenant_id_in_body',
        'organization_id_in_query',
        'nested_resource_other_tenant',
        'batch_ids_mixed_tenants',
        'alias_id_other_tenant',
    ];

    public const RESOURCES = ['customer', 'invoice', 'subscription', 'payment_method', 'report'];

    public const SYSTEM_NAMES = [
        'scheduler',
        'reconciliation',
        'horizon',
        'billing-bot',
        'mcp-billing-service',
        'unknown',
    ];

    public const MAGIC_KEYS = [
        '_tenant_id',
        'tenant_id',
        'tenantId',
        'organization_id',
        'team_id',
        'scope_id',
    ];

    public static function defaultDeriver(bool $rejectUpgrade = false): CallerDeriver
    {
        return new CallerDeriver([
            'token_abilities' => ['capabilities:cli' => 'cli'],
            'oauth' => [
                'cli-app' => 'cli',
                'mobile-app' => 'http',
            ],
            'privilege_order' => CallerDeriver::DEFAULT_PRIVILEGE_ORDER,
            'reject_upgrade_attempts' => $rejectUpgrade,
        ]);
    }

    public static function user(int|string $id = 7, ?string $tenantId = 'tenant-a'): object
    {
        $user = ResolveActor::defaultUser($id);
        if ($tenantId !== null) {
            $user->current_tenant_id = $tenantId;
        }

        return $user;
    }

    public static function system(string $name = 'scheduler'): SystemActor
    {
        return SystemActor::named($name);
    }

    /**
     * @param  array<string, mixed>  $opts
     * @return array{registry: CapabilityRegistry, fakes: SharedFakes, runCount: stdClass, queryFactory: InMemoryScopedQueryFactory, name: string}
     */
    public static function scopeHarness(array $opts = []): array
    {
        $homeTenant = $opts['tenant_id'] ?? 'tenant-a';
        $foreignTenant = $opts['foreign_tenant'] ?? 'tenant-b';
        $queryFactory = new InMemoryScopedQueryFactory;

        // Seed same-tenant + foreign resources for re-resolve checks.
        foreach (['Customer', 'Invoice', 'Subscription', 'PaymentMethod', 'Report', 'Resource'] as $model) {
            $queryFactory->put($model, [
                1 => ['tenant_id' => $homeTenant, 'data' => ['name' => 'home']],
                99 => ['tenant_id' => $foreignTenant, 'data' => ['name' => 'foreign']],
                'home' => ['tenant_id' => $homeTenant, 'data' => ['name' => 'home']],
                'foreign' => ['tenant_id' => $foreignTenant, 'data' => ['name' => 'foreign']],
            ]);
        }

        $authorizeCrossTenant = $opts['authorize_cross_tenant'] ?? true;

        $authorizeCb = $opts['authorize_cb'] ?? function ($input, $ctx) use ($queryFactory, $authorizeCrossTenant) {
            if (! $authorizeCrossTenant) {
                return true;
            }
            if (! $ctx instanceof CapabilityContext || $ctx->scope() === null) {
                return false;
            }
            $scope = $ctx->scope()->withQueryFactory($queryFactory);
            $id = null;
            if (is_object($input) && isset($input->customer_id)) {
                $id = $input->customer_id;
            } elseif (is_array($input)) {
                $id = $input['customer_id'] ?? $input['invoice_id'] ?? $input['resource_id'] ?? null;
            }
            if ($id === null) {
                return true;
            }
            $found = $scope->query('Customer')->find($id);

            return $found !== null;
        };

        $fakes = SharedFakes::create(authorize: (bool) ($opts['authorize'] ?? true));
        $resolver = $opts['scope_resolver'] ?? new DefaultScopeResolver([
            'tenancy_required' => (bool) ($opts['tenancy_required'] ?? false),
            'user_tenants' => $opts['user_tenants'] ?? [7 => $homeTenant, '7' => $homeTenant],
            'memberships' => $opts['memberships'] ?? ['7' => [$homeTenant]],
        ]);

        $registry = new CapabilityRegistry(
            globallyEnabledSurfaces: [
                'agent' => true,
                'mcp' => true,
                'http' => true,
                'cli' => true,
                'job' => true,
                'artisan' => true,
                'messaging' => false,
            ],
            validationConfig: ['validate_output' => true],
            authorizer: $opts['authorizer'] ?? $fakes->authorizer,
            approvalStore: $fakes->approvals,
            idempotencyStore: $fakes->idempotency,
            auditWriter: $fakes->audit,
            rateLimiter: $fakes->rateLimiter,
            scopeResolver: $resolver,
            auditMode: 'best_effort',
        );

        $runCount = new stdClass;
        $runCount->value = 0;
        $runCount->sideEffect = false;
        $runCount->resolved = null;

        $name = $opts['name'] ?? 'scope-cap';
        $builder = Capability::define($name)
            ->description('scope/caller/job test capability')
            ->surfaces(['agent', 'mcp', 'http', 'cli', 'job', 'artisan'])
            ->input($opts['input'] ?? CreateInvoiceInput::class)
            ->output($opts['output'] ?? CreateInvoiceResult::class)
            ->allowSystemCallers($opts['allowSystemCallers'] ?? true)
            ->authorize($authorizeCb)
            ->run(function ($in, $ctx = null) use ($runCount, $queryFactory) {
                $runCount->value++;
                $runCount->sideEffect = true;
                if ($ctx instanceof CapabilityContext && $ctx->scope() !== null) {
                    $scope = $ctx->scope()->withQueryFactory($queryFactory);
                    $id = is_object($in) && isset($in->customer_id) ? $in->customer_id : 1;
                    $runCount->resolved = $scope->query('Customer')->find($id);
                }

                return new CreateInvoiceResult(invoice_id: 42);
            });

        if (isset($opts['globalSystem']) && $opts['globalSystem']) {
            // Builder may not expose globalSystem — set via definition reflection if needed
            $builder->globalSystem(true);
        }

        $builder->register($registry);

        return [
            'registry' => $registry,
            'fakes' => $fakes,
            'runCount' => $runCount,
            'queryFactory' => $queryFactory,
            'name' => $name,
            'homeTenant' => $homeTenant,
            'foreignTenant' => $foreignTenant,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function options(string $caller = 'http', array $extra = []): array
    {
        $home = $extra['tenant_id'] ?? 'tenant-a';
        $base = [
            'caller' => $caller,
            'actor' => $caller === 'job'
                ? self::system('scheduler')
                : self::user(7, $home),
            'tenant_id' => $home,
            'require_scope' => $extra['require_scope'] ?? false,
        ];

        if ($caller === 'job') {
            $base['job'] = array_filter([
                'tenant_id' => $extra['job_tenant'] ?? $home,
            ], fn ($v) => $v !== null);
            $base['allow'] = true;
        }

        return array_merge($base, $extra);
    }

    /**
     * Input with foreign customer id (cross-tenant attack).
     *
     * @return array<string, mixed>
     */
    public static function foreignInput(): array
    {
        return [
            'customer_id' => 99,
            'amount_cents' => 100,
            'currency' => 'USD',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function homeInput(): array
    {
        return [
            'customer_id' => 1,
            'amount_cents' => 100,
            'currency' => 'USD',
        ];
    }

    public static function context(
        string $caller = 'http',
        ?object $actor = null,
        ?CapabilityScope $scope = null,
        array $extra = [],
    ): CapabilityContext {
        return CapabilityContext::make(array_merge([
            'caller' => $caller,
            'actor' => $actor ?? self::user(),
            'scope' => $scope,
        ], $extra));
    }
}
