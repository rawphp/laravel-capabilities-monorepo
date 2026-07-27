<?php

namespace Rawphp\Capabilities;

use Illuminate\Support\ServiceProvider;
use Rawphp\Capabilities\Adapters\Artisan\ArtisanCommandRegistrar;
use Rawphp\Capabilities\Adapters\Artisan\ArtisanCommandTable;
use Rawphp\Capabilities\Adapters\JobSurface;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Audit\AuditLogger;
use Rawphp\Capabilities\Boot\BootGuard;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\Boot\RegistrationPlan;
use Rawphp\Capabilities\Boot\SurfaceNames;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Contracts\Metrics;
use Rawphp\Capabilities\Contracts\ScopeResolver;
use Rawphp\Capabilities\Contracts\Tracer;
use Rawphp\Capabilities\Discovery\CapabilityDiscoveryBoot;
use Rawphp\Capabilities\Http\HttpRouteRegistrar;
use Illuminate\Database\ConnectionInterface;
use Rawphp\Capabilities\Observability\InMemoryTracer;
use Rawphp\Capabilities\Observability\LogFallbackMetrics;
use Rawphp\Capabilities\Persistence\TableGateway;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\DefaultScopeResolver;

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

            return new PeerVersionProbe(supportedVersions: $support);
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

            return ContainerBindings::makeApprovalManager(
                $config,
                self::boundTableGatewayOrNull($app),
                self::boundConnectionOrNull($app, $config, 'approval'),
            );
        });
        $this->app->alias(ApprovalManager::class, 'ApprovalManager');

        $this->app->singleton(CapabilityRegistry::class, function ($app) {
            $config = self::configFromApp($app);
            /** @var ApprovalManager $approval */
            $approval = $app->make(ApprovalManager::class);
            /** @var IdempotencyStore $idempotency */
            $idempotency = $app->make(IdempotencyStore::class);

            return ContainerBindings::makeRegistry(
                $config,
                self::boundTableGatewayOrNull($app),
                $approval->store(),
                $idempotency,
                self::boundConnectionOrNull($app, $config, null),
            );
        });
        $this->app->alias(CapabilityRegistry::class, 'CapabilityRegistry');
        // CapabilityController type-hints CapabilityBus — same singleton, no second registry (REQ-057).
        $this->app->alias(CapabilityRegistry::class, CapabilityBus::class);
        $this->app->alias(CapabilityRegistry::class, 'CapabilityBus');
    }

    /**
     * Host-bound TableGateway override (ArrayTableGateway in unit tests, custom backends).
     * Unbound → null so factories build QueryTableGateway from connection.
     */
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
     * Resolve Illuminate connection for QueryTableGateway construction.
     *
     * Order: bound ConnectionInterface → config connection name via db manager → null.
     *
     * @param  array<string, mixed>  $config
     * @param  'approval'|'idempotency'|null  $storeKey
     */
    private static function boundConnectionOrNull(object $app, array $config, ?string $storeKey): ?ConnectionInterface
    {
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

        try {
            $connection = $app->make(ConnectionInterface::class);
            if ($connection instanceof ConnectionInterface) {
                return $connection;
            }
        } catch (\Throwable) {
            // try db manager next
        }

        $name = null;
        if ($storeKey === 'approval') {
            $name = $config['approval']['connection'] ?? null;
        } elseif ($storeKey === 'idempotency') {
            $name = $config['idempotency']['connection'] ?? null;
        }
        if ($name === null || $name === '') {
            $name = $config['database']['connection'] ?? $config['connection'] ?? null;
        }
        if (is_string($name) && $name === '') {
            $name = null;
        }

        try {
            $db = $app->make('db');
            if (is_object($db) && method_exists($db, 'connection')) {
                $connection = $db->connection(is_string($name) ? $name : null);
                if ($connection instanceof ConnectionInterface) {
                    return $connection;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public function boot(): void
    {
        $this->bootHttpRoutes();
        $this->bootCapabilityDiscovery();
        $this->bootArtisanCommands();

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
     * Register in-server Artisan ops commands from ArtisanCommandTable (REQ-024).
     *
     * @return list<class-string>
     */
    public function bootArtisanCommands(?array $artisanConfig = null): array
    {
        $config = $artisanConfig ?? (self::configFromApp($this->app)['surfaces']['artisan'] ?? []);
        if (! is_array($config)) {
            $config = [];
        }

        $classes = ArtisanCommandRegistrar::classes($config);
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

        return CapabilityDiscoveryBoot::run($registry, $config);
    }

    /**
     * Map {@see \Rawphp\Capabilities\Http\RouteTable} onto the app router when http is enabled (REQ-021 / D-009).
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

        if (! is_object($router) || ! method_exists($router, 'addRoute') && ! method_exists($router, 'match')) {
            return HttpRouteRegistrar::registeredKeys($config);
        }

        return HttpRouteRegistrar::registerInto($config, function (array $def) use ($router): void {
            $method = $def['method'];
            $uri = $def['uri'];
            $action = [
                'uses' => $def['uses'][0].'@'.$def['uses'][1],
                'as' => $def['name'],
                'middleware' => $def['middleware'],
            ];

            if (method_exists($router, 'addRoute')) {
                $route = $router->addRoute($method, $uri, $action);
                if (is_object($route) && method_exists($route, 'middleware')) {
                    $route->middleware($def['middleware']);
                }

                return;
            }

            // Fallback: Router::match([$methods], $uri, $action)
            if (method_exists($router, 'match')) {
                $router->match([$method], $uri, $action);
            }
        });
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
     * @param  object  $app
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
