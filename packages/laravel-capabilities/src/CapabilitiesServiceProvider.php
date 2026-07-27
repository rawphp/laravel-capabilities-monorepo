<?php

namespace Rawphp\Capabilities;

use Illuminate\Support\ServiceProvider;
use Rawphp\Capabilities\Adapters\Artisan\ArtisanCommandTable;
use Rawphp\Capabilities\Adapters\JobSurface;

/**
 * Core package service provider.
 *
 * Job + Artisan surfaces register only when their surface flags are enabled
 * (SURF-003). Registration lists are pure tables unit-tested without a DB.
 *
 * @see docs/spec.md Package layout
 */
class CapabilitiesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/capabilities.php', 'capabilities');
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

        // Host apps map JobSurface::registeredHelpers / ArtisanCommandTable::commands
        // onto queue + console wiring. Pure lists stay unit-testable (no full boot).
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
}
