<?php

namespace Rawphp\CapabilitiesMessaging;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Support\ServiceProvider;
use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Contracts\ConversationIdentity;
use Rawphp\Capabilities\Contracts\ConversationIngress;
use Rawphp\Capabilities\Contracts\ConversationReply;
use Rawphp\CapabilitiesMessaging\Boot\MessagingBindings;
use Rawphp\CapabilitiesMessaging\Boot\MessagingRegistration;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier;
use Rawphp\CapabilitiesMessaging\Support\FakeQueue;
use Rawphp\CapabilitiesMessaging\Support\LaravelUpdateQueue;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\UpdateQueue;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdateJob;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramCallbackSigner;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramWebhookController;
use Rawphp\CapabilitiesMessaging\Threads\ThreadStore;
use RuntimeException;

/**
 * Messaging sibling package (D-007).
 *
 * Requires rawphp/laravel-capabilities; never embeds domain run().
 * Secrets are not validated at boot (D-021).
 *
 * Production bindings (L-004): when telegram is enabled, register() binds
 * MessagingConfig, UpdateQueue (Laravel bus job), TelegramBotClient (HTTP),
 * ProcessTelegramUpdate, and related services. FakeQueue / FakeTelegramBotClient
 * only when queue_driver/bot_driver=fake or APP_ENV=testing (auto).
 *
 * L-006 residual: IdentityLinker and ThreadStore remain process-local in-memory;
 * durable DB stores are deferred — see README.
 *
 * Container wiring is unit-tested via {@see MessagingBindings} /
 * {@see registrationPlan()} without booting Laravel.
 */
class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/capabilities-messaging.php', 'capabilities-messaging');

        $this->app->singleton(MessagingConfig::class, function ($app) {
            $config = $app['config']->get('capabilities-messaging', []);
            $env = method_exists($app, 'environment') ? (string) $app->environment() : 'production';

            return MessagingConfig::fromArray(is_array($config) ? $config : [], $env);
        });

        // Always bind factories; concretes respect drivers (fake only in testing/auto or explicit fake).
        $this->app->singleton(TelegramBotClient::class, function ($app) {
            /** @var MessagingConfig $cfg */
            $cfg = $app->make(MessagingConfig::class);
            $raw = $app['config']->get('capabilities-messaging', []);
            $drivers = MessagingBindings::resolveDrivers(is_array($raw) ? $raw : [], $cfg->appEnv());

            return MessagingBindings::makeBot($cfg, $drivers['bot']);
        });

        $this->app->singleton(UpdateQueue::class, function ($app) {
            /** @var MessagingConfig $cfg */
            $cfg = $app->make(MessagingConfig::class);
            $raw = $app['config']->get('capabilities-messaging', []);
            $drivers = MessagingBindings::resolveDrivers(is_array($raw) ? $raw : [], $cfg->appEnv());

            if ($drivers['queue'] === 'fake') {
                return new FakeQueue;
            }

            return new LaravelUpdateQueue(function (string $job, array $payload) use ($app): void {
                $update = $payload['update'] ?? $payload;
                if (! is_array($update)) {
                    throw new RuntimeException('UpdateQueue payload must include an update array.');
                }

                if ($app->bound(BusDispatcher::class)) {
                    $app->make(BusDispatcher::class)->dispatch(new ProcessTelegramUpdateJob($update));

                    return;
                }

                throw new RuntimeException(
                    'Production UpdateQueue requires Illuminate\Contracts\Bus\Dispatcher. '
                    .'Bind the Laravel bus or set capabilities-messaging.queue_driver=fake for tests.'
                );
            });
        });

        $this->app->singleton(ThreadStore::class, static fn () => new ThreadStore);
        $this->app->singleton(IdentityLinker::class, function ($app) {
            return new IdentityLinker($app->make(MessagingConfig::class));
        });
        $this->app->alias(IdentityLinker::class, ConversationIdentity::class);

        $this->app->singleton(TelegramCallbackSigner::class, function ($app) {
            /** @var MessagingConfig $cfg */
            $cfg = $app->make(MessagingConfig::class);
            $secret = $cfg->webhookSecret() ?? 'deferred-unset';

            return new TelegramCallbackSigner($secret, $cfg->callbackTtlSeconds());
        });

        $this->app->singleton(TelegramAdapter::class, function ($app) {
            return new TelegramAdapter($app->make(TelegramBotClient::class));
        });
        $this->app->alias(TelegramAdapter::class, ConversationIngress::class);
        $this->app->alias(TelegramAdapter::class, ConversationReply::class);

        $this->app->singleton(TelegramApprovalNotifier::class, function ($app) {
            return new TelegramApprovalNotifier(
                $app->make(MessagingConfig::class),
                $app->make(TelegramBotClient::class),
                $app->make(TelegramCallbackSigner::class),
            );
        });
        $this->app->alias(TelegramApprovalNotifier::class, ApprovalNotifier::class);

        $this->app->singleton(TelegramWebhookController::class, function ($app) {
            return new TelegramWebhookController(
                $app->make(MessagingConfig::class),
                $app->make(UpdateQueue::class),
            );
        });

        $this->app->singleton(ProcessTelegramUpdate::class, function ($app) {
            $registry = $app->bound(CapabilityBus::class) ? $app->make(CapabilityBus::class) : null;

            return new ProcessTelegramUpdate(
                $app->make(MessagingConfig::class),
                $app->make(IdentityLinker::class),
                $app->make(ThreadStore::class),
                $app->make(TelegramAdapter::class),
                $registry,
                $app->make(TelegramBotClient::class),
            );
        });
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
        $resolved = MessagingBindings::resolve($config, $appEnv);
        $built = MessagingBindings::build($config, $appEnv);
        $plan['singleton_keys'] = MessagingBindings::singletonKeys();
        $plan['bindings_built'] = array_keys($built);
        $plan['aliases'] = $built['aliases'];
        $plan['register_bindings'] = $resolved['register_bindings'];
        $plan['binding_concretes'] = $resolved['bindings'];
        $plan['drivers'] = $resolved['drivers'];
        $plan['residuals'] = $resolved['residuals'];

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
