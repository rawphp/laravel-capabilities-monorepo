<?php

namespace Rawphp\CapabilitiesMessaging\Boot;

use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Contracts\ConversationIdentity;
use Rawphp\Capabilities\Contracts\ConversationIngress;
use Rawphp\Capabilities\Contracts\ConversationReply;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier;
use Rawphp\CapabilitiesMessaging\Support\FakeQueue;
use Rawphp\CapabilitiesMessaging\Support\FakeTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\HttpTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\LaravelUpdateQueue;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\UpdateQueue;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramCallbackSigner;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramWebhookController;
use Rawphp\CapabilitiesMessaging\Threads\ThreadStore;
use RuntimeException;

/**
 * Builds messaging services from config without a Laravel container (unit-testable).
 *
 * Driver selection (L-004):
 * - queue_driver: auto | laravel | fake  (auto → fake in testing, laravel otherwise)
 * - bot_driver:   auto | http | fake     (auto → fake in testing, http otherwise)
 *
 * L-006 residual: IdentityLinker and ThreadStore remain process-local in-memory.
 * Durable DB-backed identity/thread stores are deferred — not silent.
 */
final class MessagingBindings
{
    public const L006_RESIDUAL =
        'L-006 residual: IdentityLinker and ThreadStore are process-local in-memory (not durable). '
        .'Durable DB-backed identity/thread stores are deferred.';

    /**
     * @param  array<string, mixed>  $config
     * @return array{
     *     drivers: array{queue: string, bot: string},
     *     bindings: array<string, class-string>,
     *     register_bindings: bool,
     *     telegram_enabled: bool,
     *     residuals: array{L-006: string},
     *     singleton_keys: list<string>
     * }
     */
    public static function resolve(array $config = [], string $appEnv = 'testing'): array
    {
        $cfg = MessagingConfig::fromArray($config, $appEnv);
        $drivers = self::resolveDrivers($config, $appEnv);
        $queueConcrete = $drivers['queue'] === 'fake' ? FakeQueue::class : LaravelUpdateQueue::class;
        $botConcrete = $drivers['bot'] === 'fake' ? FakeTelegramBotClient::class : HttpTelegramBotClient::class;

        $bindings = [
            MessagingConfig::class => MessagingConfig::class,
            TelegramBotClient::class => $botConcrete,
            UpdateQueue::class => $queueConcrete,
            ThreadStore::class => ThreadStore::class,
            IdentityLinker::class => IdentityLinker::class,
            ConversationIdentity::class => IdentityLinker::class,
            TelegramCallbackSigner::class => TelegramCallbackSigner::class,
            TelegramAdapter::class => TelegramAdapter::class,
            ConversationIngress::class => TelegramAdapter::class,
            ConversationReply::class => TelegramAdapter::class,
            TelegramApprovalNotifier::class => TelegramApprovalNotifier::class,
            ApprovalNotifier::class => TelegramApprovalNotifier::class,
            TelegramWebhookController::class => TelegramWebhookController::class,
            ProcessTelegramUpdate::class => ProcessTelegramUpdate::class,
        ];

        return [
            'drivers' => $drivers,
            'bindings' => $bindings,
            'register_bindings' => $cfg->telegramEnabled(),
            'telegram_enabled' => $cfg->telegramEnabled(),
            'residuals' => [
                'L-006' => self::L006_RESIDUAL,
            ],
            'singleton_keys' => self::singletonKeys(),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{queue: string, bot: string}
     */
    public static function resolveDrivers(array $config = [], string $appEnv = 'testing'): array
    {
        $queue = strtolower((string) ($config['queue_driver'] ?? 'auto'));
        $bot = strtolower((string) ($config['bot_driver'] ?? 'auto'));

        if ($queue === 'auto' || $queue === '') {
            $queue = $appEnv === 'testing' ? 'fake' : 'laravel';
        }
        if ($bot === 'auto' || $bot === '') {
            $bot = $appEnv === 'testing' ? 'fake' : 'http';
        }

        if (! in_array($queue, ['fake', 'laravel'], true)) {
            throw new RuntimeException(
                "Unknown capabilities-messaging queue_driver '{$queue}'. Use auto|laravel|fake."
            );
        }
        if (! in_array($bot, ['fake', 'http'], true)) {
            throw new RuntimeException(
                "Unknown capabilities-messaging bot_driver '{$bot}'. Use auto|http|fake."
            );
        }

        return ['queue' => $queue, 'bot' => $bot];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  (callable(string $job, array<string, mixed> $payload): void)|null  $queueDispatcher
     * @param  (callable(string $method, array<string, mixed> $params, string $token): array<string, mixed>)|null  $httpTransport
     * @return array{
     *     config: MessagingConfig,
     *     bot: TelegramBotClient,
     *     queue: UpdateQueue,
     *     threads: ThreadStore,
     *     identity: IdentityLinker,
     *     signer: TelegramCallbackSigner,
     *     adapter: TelegramAdapter,
     *     notifier: TelegramApprovalNotifier,
     *     webhook: TelegramWebhookController,
     *     processor: ProcessTelegramUpdate,
     *     aliases: array<string, class-string>,
     *     residuals: array{L-006: string},
     *     drivers: array{queue: string, bot: string}
     * }
     */
    public static function build(
        array $config = [],
        string $appEnv = 'testing',
        ?callable $queueDispatcher = null,
        ?callable $httpTransport = null,
    ): array {
        $cfg = MessagingConfig::fromArray($config, $appEnv);
        $drivers = self::resolveDrivers($config, $appEnv);

        $bot = self::makeBot($cfg, $drivers['bot'], $httpTransport);
        $queue = self::makeQueue($drivers['queue'], $queueDispatcher);

        // L-006 residual: identity + threads stay in-memory process-local.
        $threads = new ThreadStore;
        $identity = new IdentityLinker($cfg);
        $secret = $cfg->webhookSecret() ?? 'deferred-unset';
        $signer = new TelegramCallbackSigner($secret, $cfg->callbackTtlSeconds());
        $adapter = new TelegramAdapter($bot);
        $notifier = new TelegramApprovalNotifier($cfg, $bot, $signer);
        $webhook = new TelegramWebhookController($cfg, $queue);
        $processor = new ProcessTelegramUpdate($cfg, $identity, $threads, $adapter, null, $bot);

        $queueConcrete = $drivers['queue'] === 'fake' ? FakeQueue::class : LaravelUpdateQueue::class;
        $botConcrete = $drivers['bot'] === 'fake' ? FakeTelegramBotClient::class : HttpTelegramBotClient::class;

        return [
            'config' => $cfg,
            'bot' => $bot,
            'queue' => $queue,
            'threads' => $threads,
            'identity' => $identity,
            'signer' => $signer,
            'adapter' => $adapter,
            'notifier' => $notifier,
            'webhook' => $webhook,
            'processor' => $processor,
            'aliases' => [
                ConversationIdentity::class => IdentityLinker::class,
                ConversationIngress::class => TelegramAdapter::class,
                ConversationReply::class => TelegramAdapter::class,
                ApprovalNotifier::class => TelegramApprovalNotifier::class,
                TelegramBotClient::class => $botConcrete,
                UpdateQueue::class => $queueConcrete,
            ],
            'residuals' => [
                'L-006' => self::L006_RESIDUAL,
            ],
            'drivers' => $drivers,
        ];
    }

    /**
     * @param  (callable(string $method, array<string, mixed> $params, string $token): array<string, mixed>)|null  $httpTransport
     */
    public static function makeBot(
        MessagingConfig $config,
        string $driver,
        ?callable $httpTransport = null,
    ): TelegramBotClient {
        return match ($driver) {
            'fake' => new FakeTelegramBotClient,
            'http' => new HttpTelegramBotClient($config, $httpTransport),
            default => throw new RuntimeException("Unknown bot driver '{$driver}'."),
        };
    }

    /**
     * @param  (callable(string $job, array<string, mixed> $payload): void)|null  $dispatcher
     */
    public static function makeQueue(string $driver, ?callable $dispatcher = null): UpdateQueue
    {
        return match ($driver) {
            'fake' => new FakeQueue,
            'laravel' => new LaravelUpdateQueue(
                $dispatcher ?? static function (string $job, array $payload): void {
                    throw new RuntimeException(
                        'LaravelUpdateQueue requires an injected dispatcher (bind Bus in MessagingServiceProvider).'
                    );
                }
            ),
            default => throw new RuntimeException("Unknown queue driver '{$driver}'."),
        };
    }

    /**
     * @return list<string>
     */
    public static function singletonKeys(): array
    {
        return [
            MessagingConfig::class,
            TelegramBotClient::class,
            UpdateQueue::class,
            ThreadStore::class,
            IdentityLinker::class,
            ConversationIdentity::class,
            TelegramCallbackSigner::class,
            TelegramAdapter::class,
            ConversationIngress::class,
            ConversationReply::class,
            TelegramApprovalNotifier::class,
            ApprovalNotifier::class,
            TelegramWebhookController::class,
            ProcessTelegramUpdate::class,
        ];
    }
}
