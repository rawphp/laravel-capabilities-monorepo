<?php

namespace Rawphp\Capabilities;

use Illuminate\Support\ServiceProvider;
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
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Contracts\Metrics;
use Rawphp\Capabilities\Contracts\ScopeResolver;
use Rawphp\Capabilities\Contracts\Tracer;
use Rawphp\Capabilities\Observability\InMemoryTracer;
use Rawphp\Capabilities\Observability\LogFallbackMetrics;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;

/**
 * Core package service provider.
 *
 * Boot rules fail closed when peers are missing while surfaces are enabled (D-011).
 * Disabled surfaces register nothing (SURF-003). Pure registration tables stay unit-testable.
 *
 * @see docs/spec.md Package layout
 */
class CapabilitiesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/capabilities.php', 'capabilities');

        $this->app->singleton(PeerVersionProbe::class, static fn () => new PeerVersionProbe);

        $this->app->singleton(Metrics::class, function ($app) {
            $enabled = (bool) ($app['config']->get('capabilities.observability.metrics') ?? true);

            return new LogFallbackMetrics($enabled);
        });
        $this->app->alias(Metrics::class, 'Metrics');

        $this->app->singleton(Tracer::class, function ($app) {
            $enabled = (bool) ($app['config']->get('capabilities.observability.tracing') ?? true);

            return new InMemoryTracer($enabled);
        });
        $this->app->alias(Tracer::class, 'Tracer');

        $this->app->singleton(IdempotencyStore::class, static fn () => new InMemoryIdempotencyStore);
        $this->app->alias(IdempotencyStore::class, 'IdempotencyStore');

        $this->app->singleton(ScopeResolver::class, static fn () => new DefaultScopeResolver);
        $this->app->alias(ScopeResolver::class, 'ScopeResolver');

        $this->app->singleton(AuditLogger::class, static fn () => new AuditLogger);
        $this->app->alias(AuditLogger::class, 'AuditLogger');

        $this->app->singleton(CapabilityRegistry::class, static fn () => new CapabilityRegistry);
        $this->app->alias(CapabilityRegistry::class, 'CapabilityRegistry');

        $this->app->singleton(ApprovalManager::class, static fn () => ApprovalManager::inMemory());
        $this->app->alias(ApprovalManager::class, 'ApprovalManager');
    }

    public function boot(): void
    {
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
}
