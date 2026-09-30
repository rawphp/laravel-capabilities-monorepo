<?php

namespace Rawphp\Capabilities;

use ArrayAccess;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;
use Rawphp\Capabilities\Adapters\Ai\AiToolAdapter;
use Rawphp\Capabilities\Adapters\Ai\AiToolAdapterV1;
use Rawphp\Capabilities\Adapters\Artisan\ArtisanCommandRegistrar;
use Rawphp\Capabilities\Adapters\Artisan\ArtisanCommandTable;
use Rawphp\Capabilities\Adapters\Artisan\CacheCapabilitiesCommand;
use Rawphp\Capabilities\Adapters\Artisan\ClearCapabilitiesCommand;
use Rawphp\Capabilities\Adapters\Http\ApprovalController;
use Rawphp\Capabilities\Adapters\Http\AuthController;
use Rawphp\Capabilities\Adapters\Http\CapabilityController;
use Rawphp\Capabilities\Adapters\Http\IlluminateApprovalController;
use Rawphp\Capabilities\Adapters\Http\IlluminateAuthController;
use Rawphp\Capabilities\Adapters\Http\IlluminateCapabilityController;
use Rawphp\Capabilities\Adapters\JobSurface;
use Rawphp\Capabilities\Adapters\Mcp\McpAuthProfileResolver;
use Rawphp\Capabilities\Adapters\Mcp\McpServerRegistrar;
use Rawphp\Capabilities\Adapters\Mcp\McpToolAdapter;
use Rawphp\Capabilities\Adapters\Mcp\McpToolAdapterV1;
use Rawphp\Capabilities\Adapters\PeerIncompatibleException;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\OriginalActorAuthorizer;
use Rawphp\Capabilities\Approval\ResumeSchedulePlan;
use Rawphp\Capabilities\Audit\AuditLogger;
use Rawphp\Capabilities\Boot\BootGuard;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\Boot\RegistrationPlan;
use Rawphp\Capabilities\Boot\SurfaceNames;
use Rawphp\Capabilities\Contracts\ApprovalGateway;
use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Contracts\Authorizer;
use Rawphp\Capabilities\Contracts\AuthTokenIssuer;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Contracts\Metrics;
use Rawphp\Capabilities\Contracts\RateLimitCache;
use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\Capabilities\Contracts\ScopeResolver;
use Rawphp\Capabilities\Contracts\Tracer;
use Rawphp\Capabilities\Discovery\CapabilityDiscoveryBoot;
use Rawphp\Capabilities\Discovery\DiscoveryManifest;
use Rawphp\Capabilities\Http\HttpAuthGate;
use Rawphp\Capabilities\Http\HttpRouteRegistrar;
use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Observability\InMemoryTracer;
use Rawphp\Capabilities\Observability\LogFallbackMetrics;
use Rawphp\Capabilities\Persistence\DatabaseAuditWriter;
use Rawphp\Capabilities\Persistence\TableGateway;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Support\IlluminateRateLimitCache;

/**
 * Core package service provider.
 *
 * Boot rules fail closed when peers are missing while surfaces are enabled (D-011).
 * Disabled surfaces register nothing (SURF-003). Pure registration tables stay unit-testable.
 * Container bindings are a pure function of config/capabilities.php (REQ-023).
 *
 * @see docs/spec.md Package layout
 */
class CapabilitiesServiceProvider extends ServiceProvider
{
    /**
     * Container tag sibling packages / hosts use to register extra {@see ApprovalNotifier}s
     * on the single ApprovalManager (L-101). The contract binding itself is also attached.
     */
    public const APPROVAL_NOTIFIER_TAG = ApprovalNotifier::CONTAINER_TAG;

    /** Memoised so the registry and the ApprovalManager share one writer (D-010). */
    private ?AuditWriter $auditWriter = null;

    private bool $auditWriterResolved = false;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/capabilities.php', 'capabilities');

        $this->app->singleton(PeerVersionProbe::class, static function ($app) {
            $support = null;
            if (isset($app['config'])) {
                $configured = $app['config']->get('capabilities.peers.support');
                if (is_array($configured) && $configured !== []) {
                    $support = $configured;
                }
            }

            return PeerVersionProbe::fromComposer(supportedVersions: $support);
        });

        $this->app->singleton(Metrics::class, function ($app) {
            $config = self::configFromApp($app);
            $enabled = (bool) ($config['observability']['metrics'] ?? true);

            return new LogFallbackMetrics($enabled);
        });
        $this->app->alias(Metrics::class, 'Metrics');

        $this->app->singleton(Tracer::class, function ($app) {
            $config = self::configFromApp($app);
            $enabled = (bool) ($config['observability']['tracing'] ?? true);

            return new InMemoryTracer($enabled);
        });
        $this->app->alias(Tracer::class, 'Tracer');

        // TableGateway is NOT bound to ArrayTableGateway by default (REQ-051).
        // Host may bind TableGateway for unit isolation, or we resolve ConnectionInterface /
        // db.connection and build per-table QueryTableGateway in the factories.
        // When both approval and idempotency are database, each store gets its own table gateway
        // (capabilities_approvals vs capabilities_idempotency) unless host injects one gateway.

        $this->app->singleton(IdempotencyStore::class, function ($app) {
            $config = self::configFromApp($app);

            return ContainerBindings::makeIdempotencyStore(
                $config,
                self::boundTableGatewayOrNull($app),
                self::boundConnectionOrNull($app, $config, 'idempotency'),
            );
        });
        $this->app->alias(IdempotencyStore::class, 'IdempotencyStore');

        $this->app->singleton(ScopeResolver::class, static fn () => new DefaultScopeResolver);
        $this->app->alias(ScopeResolver::class, 'ScopeResolver');

        $this->app->singleton(AuditLogger::class, function ($app) {
            return ContainerBindings::makeAuditLogger(self::configFromApp($app));
        });
        $this->app->alias(AuditLogger::class, 'AuditLogger');

        // ApprovalManager before CapabilityRegistry so the registry reuses the same store.
        $this->app->singleton(ApprovalManager::class, function ($app) {
            $config = self::configFromApp($app);

            // Accept / resume run the stored invoke through the registry (D-006), after
            // re-authorizing the original requester (re-validation step 4). Both resolved
            // lazily: the registry itself is built from this manager's store.
            $manager = ContainerBindings::makeApprovalManager(
                $config,
                self::boundTableGatewayOrNull($app),
                self::boundConnectionOrNull($app, $config, 'approval'),
            )->withExecutor(static function (array $row) use ($app): CapabilityResult {
                /** @var CapabilityRegistry $registry */
                $registry = $app->make(CapabilityRegistry::class);

                return $registry->executeApproval($row);
            })->withOriginalAuthorizer(static fn (array $row): bool => self::originalActorAllows($app, $row))
                ->withAudit($this->auditWriterOrNull($app, $config))
                ->withEventDispatcher(self::eventDispatcherOrNull($app, $config))
                // Approvers are placed by the host's ScopeResolver — the one the registry stamps rows with (M-301).
                ->withScopeResolver($app->make(ScopeResolver::class));

            // Approvers are told about pending rows through every notifier the host or a
            // sibling package registered (L-101 / D-006): the ApprovalNotifier contract binding
            // and anything tagged APPROVAL_NOTIFIER_TAG. The registry adopts this instance.
            foreach (self::boundApprovalNotifiers($app) as $notifier) {
                $manager->addNotifier($notifier);
            }

            return $manager;
        });
        $this->app->alias(ApprovalManager::class, 'ApprovalManager');
        // Hosts with custom actor lookup rebind this; default resolves users through
        // the default auth guard's user provider and denies when there is none.
        $this->app->singleton(OriginalActorAuthorizer::class, static function ($app) {
            /** @var CapabilityRegistry $registry */
            $registry = $app->make(CapabilityRegistry::class);

            return new OriginalActorAuthorizer($registry, static fn (string $type, string $id): ?object => self::authUserOrNull($app, $id));
        });
        // Sibling surfaces type-hint ApprovalGateway — same singleton, no second SM (D-006 / D-007).
        $this->app->alias(ApprovalManager::class, ApprovalGateway::class);
        $this->app->alias(ApprovalManager::class, 'ApprovalGateway');

        $this->app->singleton(RateLimiter::class, function ($app) {
            $config = self::configFromApp($app);

            return ContainerBindings::makeRateLimiter(
                $config,
                self::boundRateLimitCacheOrNull($app),
            );
        });
        $this->app->alias(RateLimiter::class, 'RateLimiter');

        $this->app->singleton(CapabilityRegistry::class, function ($app) {
            $config = self::configFromApp($app);
            /** @var ApprovalManager $approval */
            $approval = $app->make(ApprovalManager::class);
            /** @var IdempotencyStore $idempotency */
            $idempotency = $app->make(IdempotencyStore::class);
            /** @var RateLimiter $rateLimiter */
            $rateLimiter = $app->make(RateLimiter::class);

            $registry = ContainerBindings::makeRegistry(
                $config,
                self::boundTableGatewayOrNull($app),
                null,
                $idempotency,
                self::boundConnectionOrNull($app, $config, null),
                self::boundRateLimitCacheOrNull($app),
                $rateLimiter,
                $this->auditWriterOrNull($app, $config),
                approvalManager: $approval,
            );

            // A host-bound Authorizer gates every invoke. Read on every authorize decision, not here:
            // the host may bind it in a provider that boots after this singleton is built, or scope it per request.
            $registry->withAuthorizerResolver(static fn (): ?Authorizer => self::boundAuthorizerOrNull($app));

            return $registry->withRequesterResolver(
                // Approved rows execute as the real requester — same lookup as the accept re-check (D-006).
                static fn (string $type, string $id): ?object => self::authUserOrNull($app, $id),
            )->withEventDispatcher(self::eventDispatcherOrNull($app, $config))
                // The host's ScopeResolver binding governs every invoke and every approval decision (D-003).
                ->withScopeResolver($app->make(ScopeResolver::class));
        });
        $this->app->alias(CapabilityRegistry::class, 'CapabilityRegistry');
        // CapabilityController type-hints CapabilityBus — same singleton, no second registry (REQ-057).
        $this->app->alias(CapabilityRegistry::class, CapabilityBus::class);
        $this->app->alias(CapabilityRegistry::class, 'CapabilityBus');

        // HTTP controllers + Illuminate edge wrappers (L-001 / REQ-071).
        // Pure controllers stay unit-testable; wrappers accept Request / return JsonResponse.
        $this->app->singleton(CapabilityController::class, function ($app) {
            $config = self::configFromApp($app);
            $http = is_array($config['surfaces']['http'] ?? null) ? $config['surfaces']['http'] : [];
            // One header setting: idempotency.header (D-005 / L-011).
            $http['idempotency_header'] ??= (string) ($config['idempotency']['header'] ?? 'Idempotency-Key');
            $clients = is_array($config['clients'] ?? null) ? $config['clients'] : [];

            return new CapabilityController(
                $app->make(CapabilityBus::class),
                $clients,
                $http,
                new HttpAuthGate(['health_public' => (bool) ($http['health_public'] ?? false)]),
                $app->make(Metrics::class),
            );
        });

        $this->app->singleton(AuthController::class, function ($app) {
            $config = self::configFromApp($app);
            $http = is_array($config['surfaces']['http'] ?? null) ? $config['surfaces']['http'] : [];
            $cli = is_array($config['surfaces']['cli'] ?? null) ? $config['surfaces']['cli'] : [];

            return new AuthController($http, $cli, self::boundAuthTokenIssuerOrNull($app));
        });

        $this->app->singleton(ApprovalController::class, function ($app) {
            $config = self::configFromApp($app);
            $http = is_array($config['surfaces']['http'] ?? null) ? $config['surfaces']['http'] : [];

            return new ApprovalController(
                $app->make(ApprovalManager::class),
                $http,
                new HttpAuthGate(['health_public' => (bool) ($http['health_public'] ?? false)]),
            );
        });

        // Wrappers classify authKind from the same clients.token_abilities map CallerDeriver uses (L-110).
        $this->app->singleton(IlluminateCapabilityController::class, static fn ($app) => new IlluminateCapabilityController(
            $app->make(CapabilityController::class),
            self::tokenAbilityMap($app),
        ));
        $this->app->singleton(IlluminateAuthController::class, static fn ($app) => new IlluminateAuthController(
            $app->make(AuthController::class),
            self::tokenAbilityMap($app),
        ));
        $this->app->singleton(IlluminateApprovalController::class, static fn ($app) => new IlluminateApprovalController(
            $app->make(ApprovalController::class),
            self::tokenAbilityMap($app),
        ));

        // MCP adapter bindings (ContainerBindings plan BOOT-001) — real Laravel singletons.
        // Without these, host MCP servers resolve McpToolAdapter interface and 500 before tools exist.
        $this->app->singleton(McpAuthProfileResolver::class, function ($app) {
            $config = self::configFromApp($app);
            $auth = is_array($config['surfaces']['mcp']['auth'] ?? null)
                ? $config['surfaces']['mcp']['auth']
                : [];

            return new McpAuthProfileResolver($auth);
        });

        $this->app->singleton(McpToolAdapter::class, function ($app) {
            $config = self::configFromApp($app);
            $mcp = is_array($config['surfaces']['mcp'] ?? null) ? $config['surfaces']['mcp'] : [];
            $enabled = (bool) ($mcp['enabled'] ?? false);
            // on_incompatible=disable soft-fails peer; fail (default) requires compatible peer when surface is on.
            $requirePeer = (string) ($mcp['on_incompatible'] ?? 'fail') !== 'disable';

            return new McpToolAdapterV1(
                registry: $app->make(CapabilityRegistry::class),
                probe: $app->make(PeerVersionProbe::class),
                authResolver: $app->make(McpAuthProfileResolver::class),
                surfaceEnabled: $enabled,
                requireCompatiblePeer: $requirePeer,
                requireProfile: (bool) ($mcp['require_profile'] ?? true),
            );
        });
        $this->app->alias(McpToolAdapter::class, 'McpToolAdapter');

        // Agent adapter singleton — same config knobs as MCP (surfaces.agent.*), incl. require_profile (D-008).
        $this->app->singleton(AiToolAdapter::class, function ($app) {
            $config = self::configFromApp($app);
            $agent = is_array($config['surfaces']['agent'] ?? null) ? $config['surfaces']['agent'] : [];
            $requirePeer = (string) ($agent['on_incompatible'] ?? 'fail') !== 'disable';

            return new AiToolAdapterV1(
                registry: $app->make(CapabilityRegistry::class),
                probe: $app->make(PeerVersionProbe::class),
                surfaceEnabled: (bool) ($agent['enabled'] ?? false),
                requireCompatiblePeer: $requirePeer,
                requireProfile: (bool) ($agent['require_profile'] ?? true),
            );
        });
        $this->app->alias(AiToolAdapter::class, 'AiToolAdapter');
    }

    /**
     * `clients.token_abilities` (ability => caller) as an array<string, string>.
     *
     * @return array<string, string>
     */
    private static function tokenAbilityMap(object $app): array
    {
        $config = self::configFromApp($app);
        $map = $config['clients']['token_abilities'] ?? [];
        if (! is_array($map)) {
            return [];
        }

        $out = [];
        foreach ($map as $ability => $caller) {
            if (is_string($ability) && is_string($caller)) {
                $out[$ability] = $caller;
            }
        }

        return $out;
    }

    /**
     * Every ApprovalNotifier the container knows about, each instance once:
     * the contract binding plus everything tagged {@see APPROVAL_NOTIFIER_TAG}.
     *
     * @return list<ApprovalNotifier>
     */
    private static function boundApprovalNotifiers(object $app): array
    {
        $candidates = [];
        if (method_exists($app, 'bound') && $app->bound(ApprovalNotifier::class)) {
            $candidates[] = $app->make(ApprovalNotifier::class);
        }
        if (method_exists($app, 'tagged')) {
            foreach ($app->tagged(self::APPROVAL_NOTIFIER_TAG) as $tagged) {
                $candidates[] = $tagged;
            }
        }

        $notifiers = [];
        foreach ($candidates as $candidate) {
            if ($candidate instanceof ApprovalNotifier) {
                $notifiers[spl_object_id($candidate)] = $candidate;
            }
        }

        return array_values($notifiers);
    }

    /**
     * Audit sink shared by the registry pipeline and the ApprovalManager (D-010 / L-006).
     *
     * A host-bound {@see AuditWriter} wins; otherwise `audit.driver=database` gets the
     * first-party {@see DatabaseAuditWriter} on the
     * default connection. Null only for the memory driver or when no connection exists —
     * {@see ContainerBindings::makeRegistry} then fails boot for strict/required audit.
     *
     * @param  array<string, mixed>  $config
     */
    private function auditWriterOrNull(object $app, array $config): ?AuditWriter
    {
        if ($this->auditWriterResolved) {
            return $this->auditWriter;
        }
        $this->auditWriterResolved = true;

        try {
            if (method_exists($app, 'bound') && $app->bound(AuditWriter::class)) {
                $bound = $app->make(AuditWriter::class);
                if ($bound instanceof AuditWriter) {
                    return $this->auditWriter = $bound;
                }
            }
        } catch (\Throwable) {
            // fall through to the package writer
        }

        return $this->auditWriter = ContainerBindings::makeAuditWriter(
            $config,
            self::boundTableGatewayOrNull($app),
            self::boundConnectionOrNull($app, $config, null),
        );
    }

    /**
     * The app's event dispatcher for bus events (D-010 §5 / L-007) when `events.enabled`.
     * Null (events off, or no `events` binding) keeps events in the registry's in-memory window.
     *
     * @param  array<string, mixed>  $config
     */
    private static function eventDispatcherOrNull(object $app, array $config): ?EventDispatcher
    {
        if (! (bool) ($config['events']['enabled'] ?? true)) {
            return null;
        }

        try {
            if (method_exists($app, 'bound') && $app->bound('events')) {
                $events = $app->make('events');

                return $events instanceof EventDispatcher ? $events : null;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Host-bound AuthTokenIssuer for CLI/API token issuance (L-002).
     * Unbound → AuthController fails closed with not_configured.
     */
    private static function boundAuthTokenIssuerOrNull(object $app): ?AuthTokenIssuer
    {
        try {
            if (method_exists($app, 'bound') && ! $app->bound(AuthTokenIssuer::class)) {
                return null;
            }
            $issuer = $app->make(AuthTokenIssuer::class);

            return $issuer instanceof AuthTokenIssuer ? $issuer : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Host-bound TableGateway override (ArrayTableGateway in unit tests, custom backends).
     * Unbound → null so factories build QueryTableGateway from connection.
     */
    /**
     * @param  array<mixed>  $row
     */
    private static function originalActorAllows(mixed $app, array $row): bool
    {
        $authorizer = is_object($app) && method_exists($app, 'make') ? $app->make(OriginalActorAuthorizer::class) : null;

        return $authorizer instanceof OriginalActorAuthorizer && $authorizer($row);
    }

    /**
     * Requester lookup for the original-actor re-check: default auth guard's user
     * provider. No auth, no provider, or no user → null (the re-check then denies).
     */
    private static function authUserOrNull(mixed $app, string $id): ?object
    {
        $auth = $app instanceof ArrayAccess && isset($app['auth']) ? $app['auth'] : null;
        $guard = is_object($auth) && method_exists($auth, 'guard') ? $auth->guard() : null;
        $users = is_object($guard) && method_exists($guard, 'getProvider') ? $guard->getProvider() : null;
        $user = is_object($users) && method_exists($users, 'retrieveById') ? $users->retrieveById($id) : null;

        return is_object($user) ? $user : null;
    }

    /**
     * The host's Authorizer binding, or null when none is bound (no host gate, L-003).
     * Fails closed: a binding that throws, or resolves to something that is not an
     * Authorizer, throws instead of silently dropping the gate.
     */
    private static function boundAuthorizerOrNull(object $app): ?Authorizer
    {
        if (! method_exists($app, 'bound') || ! $app->bound(Authorizer::class)) {
            return null;
        }
        $authorizer = $app->make(Authorizer::class);
        if (! $authorizer instanceof Authorizer) {
            throw new \UnexpectedValueException(sprintf(
                'The container binding for %s resolved to %s, which does not implement it.',
                Authorizer::class,
                get_debug_type($authorizer),
            ));
        }

        return $authorizer;
    }

    private static function boundTableGatewayOrNull(object $app): ?TableGateway
    {
        try {
            if (method_exists($app, 'bound') && ! $app->bound(TableGateway::class)) {
                return null;
            }
            $gateway = $app->make(TableGateway::class);

            return $gateway instanceof TableGateway ? $gateway : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Shared cache for rate_limits.driver=cache (L-008).
     *
     * Order: bound RateLimitCache → cache.store / Cache Repository → null (fail closed in factory).
     */
    private static function boundRateLimitCacheOrNull(object $app): ?RateLimitCache
    {
        try {
            if (method_exists($app, 'bound') && $app->bound(RateLimitCache::class)) {
                $custom = $app->make(RateLimitCache::class);

                return $custom instanceof RateLimitCache ? $custom : null;
            }
        } catch (\Throwable) {
            // fall through to Illuminate cache
        }

        try {
            if (method_exists($app, 'bound') && $app->bound('cache.store')) {
                $repo = $app->make('cache.store');
                if ($repo instanceof CacheRepository) {
                    return new IlluminateRateLimitCache($repo);
                }
            }
        } catch (\Throwable) {
            // try Repository class binding
        }

        try {
            if (method_exists($app, 'bound') && $app->bound(CacheRepository::class)) {
                $repo = $app->make(CacheRepository::class);
                if ($repo instanceof CacheRepository) {
                    return new IlluminateRateLimitCache($repo);
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Resolve the Illuminate connection a database-backed store should use.
     *
     * Order (L-008): the store's own `connection` name via the `db` manager → the bound
     * ConnectionInterface → the `db` manager default. Laravel aliases ConnectionInterface to
     * `db.connection` (the default), so the configured name must be checked first or it is
     * never reachable. A configured name that cannot be resolved returns null (the factory
     * then fails closed) — never silently the default connection.
     *
     * @param  array<string, mixed>  $config
     * @param  'approval'|'idempotency'|null  $storeKey
     */
    private static function boundConnectionOrNull(object $app, array $config, ?string $storeKey): ?ConnectionInterface
    {
        $name = $storeKey === null ? null : ($config[$storeKey]['connection'] ?? null);
        if (is_string($name) && $name !== '') {
            return self::namedConnectionOrNull($app, $name);
        }

        try {
            if (method_exists($app, 'bound') && $app->bound(ConnectionInterface::class)) {
                $connection = $app->make(ConnectionInterface::class);
                if ($connection instanceof ConnectionInterface) {
                    return $connection;
                }
            }
        } catch (\Throwable) {
            // try db manager next
        }

        return self::namedConnectionOrNull($app, null);
    }

    private static function namedConnectionOrNull(object $app, ?string $name): ?ConnectionInterface
    {
        try {
            $db = $app->make('db');
            if (is_object($db) && method_exists($db, 'connection')) {
                $connection = $db->connection($name);

                return $connection instanceof ConnectionInterface ? $connection : null;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public function boot(): void
    {
        // Misconfigured surfaces fail boot before any route/tool registers (SURF-004 / D-007).
        (new BootGuard(
            config: self::configFromApp($this->app),
            messagingPackageInstalled: $this->messagingPackageInstalled(),
        ))->assertSurfaceRules();

        $this->bootHttpRoutes();
        $this->bootCapabilityDiscovery();
        $this->bootArtisanCommands();
        $this->bootResumeSchedule();
        $this->bootMcpServers();
        $this->bootDiscoveryCacheHooks();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/capabilities.php' => config_path('capabilities.php'),
            ], 'capabilities-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'capabilities-migrations');
        }
    }

    /**
     * Presence check only — core never depends on the messaging package (D-007).
     */
    protected function messagingPackageInstalled(): bool
    {
        return class_exists('Rawphp\\CapabilitiesMessaging\\MessagingServiceProvider');
    }

    /**
     * Plan MCP servers from surfaces.mcp.profiles and register profile tools on the adapter
     * (ORI-790 / D-008 / D-011 / ORI-801 / ORI-803).
     *
     * Production boot (no $sink) builds a server plan and may call {@see McpToolAdapter::register}
     * for each planned profile. It does **not** push definitions into laravel/mcp — there is no
     * peer sink like {@see HttpRouteRegistrar::registerInto}. Hosts still wire peer MCP servers
     * (e.g. Mcp::web / peer docs). Multi-profile sequential register overwrites adapter active
     * profile/tools (last profile wins); handle() then requires options['profile']. Optional $sink is for tests/host glue only.
     * Disabled surface, empty profiles/servers, or soft-disabled peer → plan nothing
     * (no half-registration). PeerIncompatibleException only when the plan is non-empty
     * and the peer is missing/incompatible with on_incompatible=fail.
     *
     * Pure entry for unit tests: pass $mcpConfig + $sink explicitly without a full app boot.
     * Static {@see bootMcpServersWith()} is preferred for unit isolation.
     *
     * @param  array<string, mixed>|null  $mcpConfig
     * @param  callable(array<string, mixed>): void|null  $sink  optional peer facade sink (not used in production boot)
     * @return list<string> planned server names (empty when disabled / no profiles)
     */
    public function bootMcpServers(?array $mcpConfig = null, ?callable $sink = null): array
    {
        $config = $mcpConfig ?? (self::configFromApp($this->app)['surfaces']['mcp'] ?? []);
        if (! is_array($config)) {
            $config = [];
        }

        if (! (bool) ($config['enabled'] ?? true)) {
            return [];
        }

        try {
            /** @var McpToolAdapter $adapter */
            $adapter = $this->app->make(McpToolAdapter::class);
        } catch (\Throwable) {
            return [];
        }

        try {
            /** @var PeerVersionProbe $probe */
            $probe = $this->app->make(PeerVersionProbe::class);
        } catch (\Throwable) {
            $probe = null;
        }

        try {
            /** @var CapabilityRegistry $registry */
            $registry = $this->app->make(CapabilityRegistry::class);
        } catch (\Throwable) {
            $registry = null;
        }

        return self::bootMcpServersWith($config, $adapter, $probe, $sink, $registry);
    }

    /**
     * Unit-testable MCP plan/register entry (no Illuminate Application required).
     *
     * Without $sink: {@see McpServerRegistrar::register} (plan + adapter tools only — no peer mount).
     * With $sink: {@see McpServerRegistrar::registerInto} for test/host glue that receives planned rows.
     * Empty plan (no profiles/servers, auto_register off, surface disabled) → [] with no
     * peer evaluation. PeerIncompatibleException is rethrown only when real servers would
     * register and the peer is missing/incompatible under on_incompatible=fail (ORI-801).
     * Optional $registry enables MCP profile allowlist validation (ORI-842 / D-024).
     *
     * @param  array<string, mixed>  $mcpConfig
     * @param  callable(array<string, mixed>): void|null  $sink
     * @return list<string>
     */
    public static function bootMcpServersWith(
        array $mcpConfig,
        McpToolAdapter $adapter,
        ?PeerVersionProbe $probe = null,
        ?callable $sink = null,
        ?CapabilityRegistry $registry = null,
    ): array {
        if (! (bool) ($mcpConfig['enabled'] ?? true)) {
            return [];
        }

        // PeerIncompatibleException propagates (fail closed) only when the plan would register servers.
        if ($sink !== null) {
            return McpServerRegistrar::registerInto($mcpConfig, $adapter, $sink, $probe, $registry);
        }

        return array_column(McpServerRegistrar::register($mcpConfig, $adapter, $probe, $registry), 'name');
    }

    /**
     * Schedule the approval crash-recovery sweep (D-006 / P2-004 / L-014).
     *
     * `approval.execution = deferred` + `approval.resume.enabled` → `capabilities:approvals-resume`
     * on the console Schedule every `resume.every_seconds` (minute granularity), without
     * overlapping. Atomic execution or `resume.enabled = false` schedules nothing.
     *
     * @param  array<string, mixed>|null  $approvalConfig
     * @return array{command: string, cron: string}|null the applied plan
     */
    public function bootResumeSchedule(?array $approvalConfig = null): ?array
    {
        $config = $approvalConfig ?? (self::configFromApp($this->app)['approval'] ?? []);
        $plan = ResumeSchedulePlan::fromConfig(is_array($config) ? $config : []);
        if ($plan === null || ! method_exists($this->app, 'afterResolving')) {
            return $plan;
        }

        $apply = static function (object $schedule) use ($plan): void {
            ResumeSchedulePlan::apply($schedule, $plan);
        };
        $this->app->afterResolving(Schedule::class, $apply);
        if (method_exists($this->app, 'resolved') && $this->app->resolved(Schedule::class)) {
            $apply($this->app->make(Schedule::class));
        }

        return $plan;
    }

    /**
     * `php artisan optimize` / `optimize:clear` run the discovery cache pair (L-015;
     * Laravel 11.27+ `optimizes()`; older hosts call the commands directly).
     */
    public function bootDiscoveryCacheHooks(): void
    {
        if (method_exists($this, 'optimizes')) {
            $this->optimizes(
                optimize: CacheCapabilitiesCommand::SIGNATURE_NAME,
                clear: ClearCapabilitiesCommand::SIGNATURE_NAME,
                key: 'capabilities',
            );
        }
    }

    /**
     * Register in-server Artisan ops commands from ArtisanCommandTable (REQ-024).
     *
     * @return list<class-string>
     */
    public function bootArtisanCommands(?array $artisanConfig = null, ?array $approvalConfig = null): array
    {
        $full = self::configFromApp($this->app);
        $config = $artisanConfig ?? ($full['surfaces']['artisan'] ?? []);
        if (! is_array($config)) {
            $config = [];
        }
        $approval = $approvalConfig ?? ($full['approval'] ?? []);

        // Infrastructure commands (discovery cache, scheduled resume sweep) register
        // regardless of the ops invoke surface flag (L-015 / L-108).
        $classes = ArtisanCommandRegistrar::all($config, is_array($approval) ? $approval : []);
        if ($classes === []) {
            return [];
        }

        if (method_exists($this, 'commands')) {
            $this->commands($classes);
        }

        return $classes;
    }

    /**
     * Auto-discover #[Capability] classes from config path into the shared registry (REQ-022 / D-017).
     *
     * @return list<string> newly registered capability names
     */
    public function bootCapabilityDiscovery(?array $config = null): array
    {
        $config ??= self::configFromApp($this->app);

        try {
            $registry = $this->app->make(CapabilityRegistry::class);
        } catch (\Throwable) {
            return [];
        }

        if (! $registry instanceof CapabilityRegistry) {
            return [];
        }

        return CapabilityDiscoveryBoot::run($registry, $config, DiscoveryManifest::pathFor($this->app));
    }

    /**
     * Map {@see RouteTable} onto the app router when http is enabled (REQ-021 / D-009).
     *
     * @return list<string> registered route keys (empty when disabled or no router)
     */
    public function bootHttpRoutes(?array $httpConfig = null): array
    {
        $config = $httpConfig ?? (self::configFromApp($this->app)['surfaces']['http'] ?? []);
        if (! is_array($config)) {
            $config = [];
        }

        if (! (bool) ($config['enabled'] ?? true)) {
            return [];
        }

        // Prefer real Illuminate router when present; otherwise no-op (unit tests use HttpRouteRegistrar directly).
        try {
            $router = $this->app->make('router');
        } catch (\Throwable) {
            return HttpRouteRegistrar::registeredKeys($config);
        }

        if (! is_object($router) || ! method_exists($router, 'addRoute')) {
            return HttpRouteRegistrar::registeredKeys($config);
        }

        return HttpRouteRegistrar::registerInto($config, $router);
    }

    /**
     * Pure registration plan for the given config (unit-test entry point).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function registrationPlan(array $config = [], ?PeerVersionProbe $probe = null): array
    {
        return RegistrationPlan::build($config, $probe);
    }

    /**
     * Run boot guards without a full Laravel app (unit tests + artisan diagnostics).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function runBootGuards(
        array $config = [],
        ?PeerVersionProbe $probe = null,
        bool $messagingPackageInstalled = false,
        string $appEnv = 'testing',
        bool $skipBootChecks = false,
    ): array {
        $config = $config === [] ? CapabilitiesConfig::defaults() : $config;

        return (new BootGuard(
            config: $config,
            probe: $probe,
            messagingPackageInstalled: $messagingPackageInstalled,
            appEnv: $appEnv,
            skipBootChecks: $skipBootChecks,
        ))->validate();
    }

    /**
     * @return list<string>
     */
    public static function bindingAbstracts(): array
    {
        return ContainerBindings::abstracts();
    }

    /**
     * @return list<string>
     */
    public static function publishTags(): array
    {
        return ContainerBindings::PUBLISH_TAGS;
    }

    /**
     * @param  array{enabled?: bool}  $jobConfig
     * @return list<class-string|string>
     */
    public static function jobHelpers(array $jobConfig = []): array
    {
        return JobSurface::registeredHelpers($jobConfig);
    }

    /**
     * @param  array{enabled?: bool}  $artisanConfig
     * @return list<array<string, mixed>>
     */
    public static function artisanCommands(array $artisanConfig = []): array
    {
        return ArtisanCommandTable::commands($artisanConfig);
    }

    /**
     * @return list<string>
     */
    public static function knownSurfaces(): array
    {
        return SurfaceNames::ALL;
    }

    /**
     * @return array<string, mixed>
     */
    private static function configFromApp(object $app): array
    {
        try {
            $config = $app->make('config');
            if (is_object($config) && method_exists($config, 'get')) {
                $value = $config->get('capabilities', []);

                return is_array($value) ? $value : CapabilitiesConfig::defaults();
            }
        } catch (\Throwable) {
            // unit fakes without config repository
        }

        return CapabilitiesConfig::defaults();
    }
}
