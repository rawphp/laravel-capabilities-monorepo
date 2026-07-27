<?php

namespace Rawphp\CapabilitiesMessaging;

use Illuminate\Support\ServiceProvider;
use Rawphp\CapabilitiesMessaging\Boot\MessagingBindings;
use Rawphp\CapabilitiesMessaging\Boot\MessagingRegistration;

/**
 * Messaging sibling package (D-007).
 *
 * Requires rawphp/laravel-capabilities; never embeds domain run().
 * Secrets are not validated at boot (D-021).
 *
 * Container wiring is unit-tested via {@see MessagingBindings} /
 * {@see registrationPlan()} without booting Laravel.
 */
class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/capabilities-messaging.php', 'capabilities-messaging');
    }

    public function boot(): void
    {
        // D-021: do not require TELEGRAM_* here — artisan migrate must work.
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/capabilities-messaging.php' => config_path('capabilities-messaging.php'),
            ], 'capabilities-messaging-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'capabilities-messaging-migrations');
        }

        if ((bool) $this->app['config']->get('capabilities-messaging.telegram.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/messaging.php');
        }
    }

    /**
     * Pure registration plan for unit tests (no container required).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function registrationPlan(array $config = [], string $appEnv = 'testing'): array
    {
        $plan = MessagingRegistration::plan($config, $appEnv);
        $built = MessagingBindings::build($config, $appEnv);
        $plan['singleton_keys'] = MessagingBindings::singletonKeys();
        $plan['bindings_built'] = array_keys($built);
        $plan['aliases'] = $built['aliases'];

        return $plan;
    }

    /**
     * @return list<string>
     */
    public static function publishTags(): array
    {
        return MessagingRegistration::PUBLISH_TAGS;
    }
}
