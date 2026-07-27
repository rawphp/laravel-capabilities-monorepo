<?php

namespace Rawphp\Capabilities;

use Illuminate\Support\ServiceProvider;

/**
 * Core package service provider (scaffold).
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

        // Routes, surface adapters, and boot checks: planned implementation.
    }
}
