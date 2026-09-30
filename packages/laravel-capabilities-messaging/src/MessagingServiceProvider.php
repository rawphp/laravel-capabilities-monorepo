<?php

namespace Rawphp\CapabilitiesMessaging;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Rawphp\Capabilities\Contracts\ApprovalGateway;
use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Contracts\ConversationIdentity;
use Rawphp\Capabilities\Contracts\ConversationIngress;
use Rawphp\Capabilities\Contracts\ConversationReply;
use Rawphp\Capabilities\Contracts\Metrics;
use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\CapabilitiesMessaging\Boot\MessagingBindings;
use Rawphp\CapabilitiesMessaging\Boot\MessagingRegistration;
use Rawphp\CapabilitiesMessaging\Contracts\AgentTurn;
use Rawphp\CapabilitiesMessaging\Identity\CacheLinkStore;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\Identity\LinkStore;
use Rawphp\CapabilitiesMessaging\Identity\ModelUserFactory;
use Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier;
use Rawphp\CapabilitiesMessaging\Support\FakeQueue;
use Rawphp\CapabilitiesMessaging\Support\LaravelUpdateQueue;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\UpdateQueue;
use Rawphp\CapabilitiesMessaging\Telegram\CallbackHandler;
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
 * Link codes and identity links use a {@see LinkStore} on the host cache repository, shared by
 * web and queue workers. L-006: the pipeline keeps no thread history (thread ids only) — see README.
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
        // Codes are issued in the web process and bound on the queue worker: shared host cache.
        $this->app->singleton(LinkStore::class, static fn ($app) => new CacheLinkStore($app->make(CacheRepository::class)));
        $this->app->singleton(IdentityLinker::class, function ($app) {
            return new IdentityLinker(
                $app->make(MessagingConfig::class),
                new ModelUserFactory(self::userModel($app)),
                metrics: $app->bound(Metrics::class) ? $app->make(Metrics::class) : null,
                store: $app->make(LinkStore::class),
            );
        });
        $this->app->alias(IdentityLinker::class, ConversationIdentity::class);

        // Resolved lazily: callbackSecret() throws when neither callback nor webhook secret is set (D-021).
        $this->app->singleton(TelegramCallbackSigner::class, function ($app) {
            /** @var MessagingConfig $cfg */
            $cfg = $app->make(MessagingConfig::class);

            return new TelegramCallbackSigner($cfg->callbackSecret(), $cfg->callbackTtlSeconds());
        });

        // Agent turn is a host binding (D-007): unbound ⇒ adapter fails closed, profile has no tools.
        $this->app->singleton(TelegramAdapter::class, function ($app) {
            $turn = self::agentTurn($app);

            return new TelegramAdapter(
                $app->make(TelegramBotClient::class),
                $turn === null ? null : static fn (array $message): array => isset($message['tool_results'])
                    ? $turn->respondWithResults(array_diff_key($message, ['tool_results' => true]), $message['tool_results'])
                    : $turn->respond($message),
            );
        });
        $this->app->alias(TelegramAdapter::class, ConversationIngress::class);
        $this->app->alias(TelegramAdapter::class, ConversationReply::class);

        // No signer injected: the notifier signs with callbackSecret() on notify, so resolving
        // the ApprovalNotifier never requires secrets at boot (D-021).
        $this->app->singleton(TelegramApprovalNotifier::class, function ($app) {
            return new TelegramApprovalNotifier(
                $app->make(MessagingConfig::class),
                $app->make(TelegramBotClient::class),
                audit: $app->bound(AuditWriter::class) ? $app->make(AuditWriter::class) : null,
            );
        });
        $this->app->alias(TelegramApprovalNotifier::class, ApprovalNotifier::class);
        // Core attaches every tagged notifier to its single ApprovalManager (M-101 / L-101).
        $this->app->tag([TelegramApprovalNotifier::class], ApprovalNotifier::CONTAINER_TAG);

        // Tapped approval buttons decide through core's ApprovalGateway port (D-006 / D-007); the
        // gateway is core's binding — unbound, the handler fails closed on use, not at boot.
        $this->app->singleton(CallbackHandler::class, function (Container $app) {
            return new CallbackHandler(
                $app->make(TelegramCallbackSigner::class),
                $app->make(IdentityLinker::class),
                $app->bound(ApprovalGateway::class) ? $app->make(ApprovalGateway::class) : null,
            );
        });

        $this->app->singleton(TelegramWebhookController::class, function ($app) {
            return new TelegramWebhookController(
                $app->make(MessagingConfig::class),
                $app->make(UpdateQueue::class),
                self::logger($app),
            );
        });

        $this->app->singleton(ProcessTelegramUpdate::class, function (Container $app) {
            $registry = $app->bound(CapabilityBus::class) ? $app->make(CapabilityBus::class) : null;

            return new ProcessTelegramUpdate(
                $app->make(MessagingConfig::class),
                $app->make(IdentityLinker::class),
                $app->make(ThreadStore::class),
                $app->make(TelegramAdapter::class),
                $registry,
                $app->make(TelegramBotClient::class),
                self::toolNamesResolver($app),
                turnLimiter: $app->bound(RateLimiter::class) ? $app->make(RateLimiter::class) : null,
                logger: self::logger($app),
                pendingReplies: $app->make(CacheRepository::class),
                // Lazy: the handler needs the callback signer, whose secret must not be required at boot (D-021).
                callbacks: static fn (): CallbackHandler => $app->make(CallbackHandler::class),
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
     * capabilities-messaging.user_model, else the auth users provider model (same rule as the AI sibling).
     */
    private static function userModel(Container $app): ?string
    {
        foreach (['capabilities-messaging.user_model', 'auth.providers.users.model'] as $key) {
            $model = $app['config']->get($key);
            if (is_string($model) && $model !== '') {
                return $model;
            }
        }

        return null;
    }

    private static function agentTurn(Container $app): ?AgentTurn
    {
        return $app->bound(AgentTurn::class) ? $app->make(AgentTurn::class) : null;
    }

    /**
     * @return (callable(string): list<string>)|null
     */
    private static function toolNamesResolver(Container $app): ?callable
    {
        $turn = self::agentTurn($app);

        return $turn === null ? null : static fn (string $profile): array => $turn->toolNames($profile);
    }

    /**
     * Host PSR-3 logger when bound (Laravel aliases LoggerInterface to `log`), else none (D-019).
     */
    private static function logger(Container $app): ?LoggerInterface
    {
        return $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null;
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
