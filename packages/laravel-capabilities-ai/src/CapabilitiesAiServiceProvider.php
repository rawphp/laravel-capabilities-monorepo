<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\CapabilitiesAi\Console\ReapStaleTurnsCommand;
use Rawphp\CapabilitiesAi\Contracts\ConversationContextProvider;
use Rawphp\CapabilitiesAi\Contracts\IdempotencyReadiness;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;
use Rawphp\CapabilitiesAi\Domain\StaleTurnReaper;
use Rawphp\CapabilitiesAi\Domain\TurnClaim;
use Rawphp\CapabilitiesAi\Domain\TurnRunner;
use Rawphp\CapabilitiesAi\Domain\TurnService;
use Rawphp\CapabilitiesAi\Support\ContainerBindings;
use Rawphp\CapabilitiesAi\Support\StoreBoundIdempotencyReadiness;
use RuntimeException;

/**
 * AI package service provider — config + migrations publish tags + optional routes + DI.
 *
 * Host seams (ConversationContextProvider, ToolCatalog) are intentionally unbound.
 * Host-prebound LlmClient / ProgressStore are preserved (bound() guard).
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

                return ContainerBindings::makeLlmClient($config);
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

        $this->app->singleton(TurnClaim::class, static fn () => new TurnClaim);

        $this->app->singleton(StaleTurnReaper::class, static fn () => new StaleTurnReaper);

        $this->app->singleton(TurnService::class, function (Container $app) {
            return ContainerBindings::makeTurnService($app->make(ProgressStore::class));
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

        $this->app->singleton(ConversationService::class, function (Container $app) {
            $config = self::configFromApp($app);

            return ContainerBindings::makeConversationService(
                self::makeDispatchCallable($app),
                $app->make(ProgressStore::class),
                ContainerBindings::claimTtlFromConfig($config),
                $config,
            );
        });

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

        if (function_exists('dispatch')) {
            return static function (object $job) use ($applyQueue): mixed {
                $applyQueue($job);

                return dispatch($job);
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
            return $manager->connection($connection);
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
     * Escape hatch for local demos: CAPABILITIES_AI_ALLOW_UNSAFE=1.
     * Default closed — never the production happy path.
     *
     * @param  array<string, mixed>  $config  capabilities-ai config slice
     */
    private static function allowUnsafeDrivers(array $config): bool
    {
        if (! empty($config['allow_unsafe'])) {
            return true;
        }

        $value = $_ENV['CAPABILITIES_AI_ALLOW_UNSAFE']
            ?? $_SERVER['CAPABILITIES_AI_ALLOW_UNSAFE']
            ?? getenv('CAPABILITIES_AI_ALLOW_UNSAFE');

        if ($value === false || $value === null || $value === '') {
            return false;
        }

        return match (strtolower((string) $value)) {
            '1', 'true', '(true)', 'yes', 'on' => true,
            default => false,
        };
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
