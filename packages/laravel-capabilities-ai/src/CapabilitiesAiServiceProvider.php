<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * AI package service provider — config + migrations publish tags + optional routes.
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
        $this->bootRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/capabilities-ai.php' => config_path('capabilities-ai.php'),
            ], 'capabilities-ai-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'capabilities-ai-migrations');
        }
    }

    private function bootRoutes(): void
    {
        $config = $this->app->make('config')->get('capabilities-ai.routes', []);
        if (! ($config['enabled'] ?? false)) {
            return;
        }

        $prefix = (string) ($config['prefix'] ?? 'capabilities-ai/chat');
        $middleware = $config['middleware'] ?? ['api', 'auth:sanctum'];

        Route::middleware($middleware)
            ->prefix($prefix)
            ->group(__DIR__.'/../routes/capabilities-ai.php');
    }
}
