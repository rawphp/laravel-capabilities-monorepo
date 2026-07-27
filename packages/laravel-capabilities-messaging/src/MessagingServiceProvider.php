<?php

namespace Rawphp\CapabilitiesMessaging;

use Illuminate\Support\ServiceProvider;

/**
 * Messaging sibling package (D-007) — scaffold.
 * Requires rawphp/laravel-capabilities; never embeds domain run().
 */
class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/capabilities-messaging.php', 'capabilities-messaging');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/capabilities-messaging.php' => config_path('capabilities-messaging.php'),
            ], 'capabilities-messaging-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'capabilities-messaging-migrations');
        }
    }
}
