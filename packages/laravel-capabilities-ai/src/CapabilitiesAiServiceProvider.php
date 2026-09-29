<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Contracts\Metrics;
use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\Capabilities\Contracts\Tracer;
use Rawphp\CapabilitiesAi\Console\ReapStaleTurnsCommand;
use Rawphp\CapabilitiesAi\Contracts\ConversationContextProvider;
use Rawphp\CapabilitiesAi\Contracts\ConversationStore;
use Rawphp\CapabilitiesAi\Contracts\IdempotencyReadiness;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Contracts\ProgressStoreReadiness;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Contracts\TurnClaim;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;
use Rawphp\CapabilitiesAi\Domain\StaleTurnReaper;
use Rawphp\CapabilitiesAi\Domain\TurnRunner;
use Rawphp\CapabilitiesAi\Domain\TurnService;
use Rawphp\CapabilitiesAi\Http\ChatController;
use Rawphp\CapabilitiesAi\Support\ContainerBindings;
use Rawphp\CapabilitiesAi\Support\EloquentConversationStore;
use Rawphp\CapabilitiesAi\Support\EloquentTurnClaim;
use Rawphp\CapabilitiesAi\Support\ResolveConversationActor;
use Rawphp\CapabilitiesAi\Support\StoreBoundIdempotencyReadiness;
use Rawphp\CapabilitiesAi\Support\StoreBoundProgressStoreReadiness;
use RuntimeException;

/**
 * AI package service provider — config + migrations publish tags + optional routes + DI.
 *
 * Host seams (ConversationContextProvider, ToolCatalog) are intentionally unbound.
 * Host-prebound LlmClient / ProgressStore / ConversationStore / TurnClaim are preserved (bound() guard).
 */
final class CapabilitiesAiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/capabilities-ai.php',
            'capabilities-ai'
        );

        $this->registerPackageBindings();
    }

    public function boot(): void
    {
        $this->assertConversationActorModel();
        $this->bootRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([ReapStaleTurnsCommand::class]);

            $this->publishes([
                __DIR__.'/../config/capabilities-ai.php' => config_path('capabilities-ai.php'),
            ], 'capabilities-ai-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'capabilities-ai-migrations');
        }
    }

    private function registerPackageBindings(): void
    {
        if (! $this->app->bound(LlmClient::class)) {
            $this->app->singleton(LlmClient::class, function (Container $app) {
                $config = self::configFromApp($app);
                // Host-prebound LlmClient skips this factory entirely (bound() guard above).
                ContainerBindings::assertSafeDrivers(
                    $config,
                    self::isTestingEnvironment($app),
                    self::allowUnsafeDrivers($config),
                );

                return ContainerBindings::makeLlmClient(
                    $config,
                    $app->bound(Metrics::class) ? $app->make(Metrics::class) : null,
                    $app->bound(Tracer::class) ? $app->make(Tracer::class) : null,
                );
            });
        }

        if (! $this->app->bound(ProgressStore::class)) {
            $this->app->singleton(ProgressStore::class, function (Container $app) {
                $config = self::configFromApp($app);
                // Host-prebound ProgressStore skips this factory entirely (bound() guard above).
                ContainerBindings::assertSafeDrivers(
                    $config,
                    self::isTestingEnvironment($app),
                    self::allowUnsafeDrivers($config),
                );
                $redis = self::resolveRedisClientOrNull($app, $config);

                return ContainerBindings::makeProgressStore($config, $redis);
            });
        }

        // Package-table persistence: Eloquent by default; one store + claim shared by every service.
        if (! $this->app->bound(ConversationStore::class)) {
            $this->app->singleton(ConversationStore::class, static fn () => new EloquentConversationStore);
        }

        if (! $this->app->bound(TurnClaim::class)) {
            $this->app->singleton(TurnClaim::class, static fn () => new EloquentTurnClaim);
        }

        $this->app->singleton(StaleTurnReaper::class, static fn (Container $app) => new StaleTurnReaper(
            $app->make(ProgressStore::class),
            $app->make(TurnClaim::class),
        ));

        $this->app->singleton(TurnService::class, function (Container $app) {
            return ContainerBindings::makeTurnService(
                $app->make(ProgressStore::class),
                $app->make(ConversationStore::class),
                $app->make(TurnClaim::class),
            );
        });

        $this->app->singleton(TurnRunner::class, function (Container $app) {
            $config = self::configFromApp($app);

            return ContainerBindings::makeTurnRunner(
                claim: $app->make(TurnClaim::class),
                llm: $app->make(LlmClient::class),
                progress: $app->make(ProgressStore::class),
                config: $config,
                context: self::optional($app, ConversationContextProvider::class),
                tools: self::optional($app, ToolCatalog::class),
                bus: self::optional($app, CapabilityBus::class),
                store: $app->make(ConversationStore::class),
            );
        });

        if (! $this->app->bound(IdempotencyReadiness::class)) {
            // Live probe of core IdempotencyStore; fail closed when unbound. AlwaysReady is tests-only.
            $this->app->singleton(IdempotencyReadiness::class, function (Container $app) {
                if ($app->bound(IdempotencyStore::class)) {
                    return StoreBoundIdempotencyReadiness::forStore(
                        $app->make(IdempotencyStore::class)
                    );
                }

                return StoreBoundIdempotencyReadiness::unbound();
            });
        }

        if (! $this->app->bound(ProgressStoreReadiness::class)) {
            // Live ping of the resolved ProgressStore; read by core capabilities:integration-health.
            $this->app->singleton(ProgressStoreReadiness::class, function (Container $app) {
                return new StoreBoundProgressStoreReadiness(
                    $app->make(ProgressStore::class),
                    self::optional($app, Metrics::class),
                );
            });
        }

        $this->app->singleton(ConversationService::class, function (Container $app) {
            $config = self::configFromApp($app);

            return ContainerBindings::makeConversationService(
                self::makeDispatchCallable($app),
                $app->make(ProgressStore::class),
                ContainerBindings::claimTtlFromConfig($config),
                $config,
                self::optional($app, RateLimiter::class),
                $app->make(ConversationStore::class),
            );
        });

        $this->app->bind(ChatController::class, static fn (Container $app) => new ChatController(
            ContainerBindings::maxMessageCharsFromConfig(self::configFromApp($app)),
        ));

        $this->app->singleton(ProposalService::class, function (Container $app) {
            if (! $app->bound(CapabilityBus::class)) {
                throw new RuntimeException(
                    'CapabilityBus must be bound (core package) before resolving ProposalService'
                );
            }

            $config = self::configFromApp($app);
            $userModel = $config['user_model'] ?? null;

            return ContainerBindings::makeProposalService(
                $app->make(CapabilityBus::class),
                $app->make(IdempotencyReadiness::class),
                is_string($userModel) && $userModel !== '' ? $userModel : null,
                self::optional($app, ToolCatalog::class),
                $app->make(ConversationStore::class),
            );
        });
    }

    /**
     * @return callable(object): mixed
     */
    private static function makeDispatchCallable(Container $app): callable
    {
        $config = self::configFromApp($app);
        $queueName = $config['queue']['name'] ?? null;
        $queueConnection = $config['queue']['connection'] ?? null;
        $queueName = is_string($queueName) && $queueName !== '' ? $queueName : null;
        $queueConnection = is_string($queueConnection) && $queueConnection !== '' ? $queueConnection : null;

        $applyQueue = static function (object $job) use ($queueName, $queueConnection): void {
            if ($queueName !== null && property_exists($job, 'queue')) {
                $job->queue = $queueName;
            }
            if ($queueConnection !== null && property_exists($job, 'connection')) {
                $job->connection = $queueConnection;
            }
        };

        if ($app->bound('Illuminate\Contracts\Bus\Dispatcher')) {
            $bus = $app->make('Illuminate\Contracts\Bus\Dispatcher');

            return static function (object $job) use ($bus, $applyQueue): mixed {
                $applyQueue($job);

                return $bus->dispatch($job);
            };
        }

        return static function (object $job): void {
            throw new RuntimeException(
                'No bus dispatcher available; bind Illuminate\\Contracts\\Bus\\Dispatcher or rebind ConversationService'
            );
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function resolveRedisClientOrNull(Container $app, array $config): ?object
    {
        $driver = strtolower((string) (($config['progress']['driver'] ?? null) ?: 'array'));
        if ($driver !== 'redis') {
            return null;
        }

        $connection = (string) ($config['progress']['redis_connection'] ?? 'default');

        if (! $app->bound('redis')) {
            return null;
        }

        $manager = $app->make('redis');
        if (is_object($manager) && method_exists($manager, 'connection')) {
            $conn = $manager->connection($connection);

            // Laravel returns Illuminate\Redis\Connections\* wrappers. RedisProgressStore
            // uses method_exists(rPush/lRange); those methods exist on the underlying
            // ext-redis/predis client, not on the connection wrapper (only via __call).
            // Prefer the native client so progress append/since work under phpredis.
            if (is_object($conn) && method_exists($conn, 'client')) {
                $client = $conn->client();
                if (is_object($client)) {
                    return $client;
                }
            }

            return is_object($conn) ? $conn : null;
        }
        if (is_object($manager)) {
            return $manager;
        }

        return null;
    }

    /**
     * @template T
     *
     * @param  class-string<T>  $abstract
     * @return T|null
     */
    private static function optional(Container $app, string $abstract): mixed
    {
        if (! $app->bound($abstract)) {
            return null;
        }

        return $app->make($abstract);
    }

    /**
     * @return array<string, mixed>
     */
    private static function configFromApp(Container $app): array
    {
        if ($app->bound('config')) {
            $config = $app->make('config');
            if (is_object($config) && method_exists($config, 'get')) {
                $slice = $config->get('capabilities-ai', []);

                return is_array($slice) ? $slice : [];
            }
        }

        return require __DIR__.'/../config/capabilities-ai.php';
    }

    /**
     * Detect testing: app()->environment('testing') when available; else APP_ENV.
     */
    private static function isTestingEnvironment(Container $app): bool
    {
        if (method_exists($app, 'environment')) {
            /** @var string|bool $result */
            $result = $app->environment('testing');

            return $result === true || $result === 'testing';
        }

        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV');

        return $env === 'testing';
    }

    /**
     * Escape hatch for local demos (CAPABILITIES_AI_ALLOW_UNSAFE → allow_unsafe).
     * Read from config only so cached config is honoured. Default closed.
     *
     * @param  array<string, mixed>  $config  capabilities-ai config slice
     */
    private static function allowUnsafeDrivers(array $config): bool
    {
        return filter_var($config['allow_unsafe'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Fail closed at boot when bus invokes are possible but the actor model is unusable.
     * Class + query() check only; no database access.
     */
    private function assertConversationActorModel(): void
    {
        if (! $this->app->bound(CapabilityBus::class)) {
            return;
        }

        $config = $this->app->make('config');
        $model = null;
        if (is_object($config) && method_exists($config, 'get')) {
            foreach (['capabilities-ai.user_model', 'auth.providers.users.model'] as $key) {
                $value = $config->get($key);
                if (is_string($value) && $value !== '') {
                    $model = $value;
                    break;
                }
            }
        }

        ResolveConversationActor::assertQueryableModel($model);
    }

    private function bootRoutes(): void
    {
        $full = $this->app->make('config')->get('capabilities-ai', []);
        $full = is_array($full) ? $full : [];
        $routes = $full['routes'] ?? [];
        if (! is_array($routes) || ! ($routes['enabled'] ?? false)) {
            return;
        }

        $prefix = (string) ($routes['prefix'] ?? 'capabilities-ai/chat');
        $middleware = $routes['middleware'] ?? ['api', 'auth:sanctum'];
        $proposalsOn = self::proposalsEnabled($full);

        Route::middleware($middleware)
            ->prefix($prefix)
            ->group(function () use ($proposalsOn): void {
                require __DIR__.'/../routes/capabilities-ai.php';
                if ($proposalsOn) {
                    require __DIR__.'/../routes/capabilities-ai-proposals.php';
                }
            });
    }

    /**
     * Single gate for proposal routes, TurnRunner fence, and history (D-024).
     *
     * @param  array<string, mixed>  $config  capabilities-ai config slice
     */
    public static function proposalsEnabled(array $config): bool
    {
        return (bool) ($config['proposals']['enabled'] ?? true);
    }
}
