<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi;

use Illuminate\Support\ServiceProvider;

/**
 * AI package service provider — config + migrations publish tags.
 *
 * Domain bindings (ProgressStore, LlmClient, etc.) land in later REQs.
 */
final class CapabilitiesAiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/capabilities-ai.php',
            'capabilities-ai'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/capabilities-ai.php' => config_path('capabilities-ai.php'),
            ], 'capabilities-ai-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'capabilities-ai-migrations');
        }
    }
}
