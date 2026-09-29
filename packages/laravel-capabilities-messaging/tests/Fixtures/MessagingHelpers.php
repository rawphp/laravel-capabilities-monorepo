<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesMessaging\Tests\Fixtures;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Contracts\ApprovalGateway;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\MessagingServiceProvider;
use Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier;
use Rawphp\CapabilitiesMessaging\Support\FakeQueue;
use Rawphp\CapabilitiesMessaging\Support\FakeTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\LinkedUser;
use Rawphp\CapabilitiesMessaging\Telegram\CallbackHandler;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramCallbackSigner;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramWebhookController;
use Rawphp\CapabilitiesMessaging\Threads\ThreadStore;

/**
 * Shared fixtures for messaging unit tests — mocks only, no network/DB.
 */
final class MessagingHelpers
{
    public const MSG_SRC = __DIR__.'/../../src';

    public const MSG_ROOT = __DIR__.'/../..';

    public const CORE_SRC = __DIR__.'/../../../laravel-capabilities/src';

    public const MONOREPO_ROOT = __DIR__.'/../../../..';

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function config(array $overrides = [], string $appEnv = 'testing'): MessagingConfig
    {
        $base = [
            'telegram' => [
                'enabled' => true,
                'bot_token' => 'test-bot-token',
                'webhook_secret' => 'test-webhook-secret',
                'callback_secret' => 'test-callback-secret',
                'callback_ttl_seconds' => 900,
            ],
            'agent_profile' => 'support',
            'identity' => [
                'mode' => 'code_link',
                'code_ttl_seconds' => 600,
                'allowlist' => [],
            ],
            'skip_boot_checks' => false,
        ];

        return MessagingConfig::fromArray(array_replace_recursive($base, $overrides), $appEnv);
    }

    public static function signer(?MessagingConfig $config = null): TelegramCallbackSigner
    {
        $config ??= self::config();

        return new TelegramCallbackSigner($config->callbackSecret(), $config->callbackTtlSeconds());
    }

    public static function bot(): FakeTelegramBotClient
    {
        return new FakeTelegramBotClient;
    }

    public static function queue(): FakeQueue
    {
        return new FakeQueue;
    }

    public static function threads(): ThreadStore
    {
        return new ThreadStore;
    }

    /**
     * @param  array<string, mixed>  $configOverrides
     */
    public static function identity(array $configOverrides = []): IdentityLinker
    {
        return new IdentityLinker(self::config($configOverrides));
    }

    public static function linkedUser(string $id = 'user-1', ?string $tenantId = 'tenant-a'): LinkedUser
    {
        return new LinkedUser(id: $id, tenantId: $tenantId);
    }

    /**
     * Test agent turn: replies with the user's text, no tool calls.
     *
     * @return callable(array<string, mixed>): array{text: string, tool_calls: list<mixed>}
     */
    public static function echoAgent(): callable
    {
        return static fn (array $message): array => ['text' => (string) ($message['text'] ?? ''), 'tool_calls' => []];
    }

    /**
     * @param  array{
     *   config?: MessagingConfig,
     *   identity?: IdentityLinker,
     *   threads?: ThreadStore,
     *   adapter?: TelegramAdapter,
     *   registry?: FakeCapabilityBus,
     *   bot?: FakeTelegramBotClient,
     *   profile_tools?: list<string>,
     * }  $parts
     */
    public static function processor(array $parts = []): ProcessTelegramUpdate
    {
        $config = $parts['config'] ?? self::config();
        $identity = $parts['identity'] ?? new IdentityLinker($config);
        $threads = $parts['threads'] ?? new ThreadStore;
        $bot = $parts['bot'] ?? new FakeTelegramBotClient;
        $adapter = $parts['adapter'] ?? new TelegramAdapter($bot, self::echoAgent());
        $registry = $parts['registry'] ?? new FakeCapabilityBus;
        $tools = $parts['profile_tools'] ?? ['support.ping'];

        return new ProcessTelegramUpdate(
            config: $config,
            identity: $identity,
            threads: $threads,
            adapter: $adapter,
            registry: $registry,
            bot: $bot,
            profileResolver: static fn (string $profile): array => $tools,
        );
    }

    /**
     * @param  array<string, mixed>  $configOverrides
     */
    public static function webhook(array $configOverrides = [], ?FakeQueue $queue = null): TelegramWebhookController
    {
        return new TelegramWebhookController(self::config($configOverrides), $queue ?? new FakeQueue);
    }

    public static function notifier(?MessagingConfig $config = null, ?FakeTelegramBotClient $bot = null): TelegramApprovalNotifier
    {
        $config ??= self::config();
        $bot ??= new FakeTelegramBotClient;

        return new TelegramApprovalNotifier($config, $bot, self::signer($config));
    }

    /**
     * Concrete manager for tests that seed rows via request/store.
     * Production messaging depends only on {@see ApprovalGateway}.
     */
    public static function approvals(): ApprovalManager
    {
        $clock = new FixedClock(new \DateTimeImmutable('2026-01-15T12:00:00Z'));
        $store = new InMemoryApprovalStore($clock);

        return new ApprovalManager($store, $clock);
    }

    public static function callbackHandler(
        ?IdentityLinker $identity = null,
        ?ApprovalGateway $approvals = null,
        ?TelegramCallbackSigner $signer = null,
    ): CallbackHandler {
        return new CallbackHandler(
            $signer ?? self::signer(),
            $identity ?? self::identity(),
            $approvals ?? self::approvals(),
        );
    }

    /**
     * DB-free container with the messaging provider registered against a fixed config array.
     * Config reports as cached, so register() skips mergeConfigFrom and the env()-driven file.
     *
     * @param  array<string, mixed>  $messagingConfig  value of config('capabilities-messaging')
     * @param  array<string, mixed>  $otherConfig  other dotted keys (e.g. auth.providers.users.model)
     * @param  CacheRepository|null  $cache  host cache store (array store by default; share one to model web + worker)
     */
    public static function container(array $messagingConfig = [], array $otherConfig = [], ?CacheRepository $cache = null): Container
    {
        $app = new class extends Container implements CachesConfiguration
        {
            public function configurationIsCached(): bool
            {
                return true;
            }

            public function getCachedConfigPath(): string
            {
                return '';
            }

            public function getCachedServicesPath(): string
            {
                return '';
            }

            public function environment(): string
            {
                return 'testing';
            }
        };
        $values = ['capabilities-messaging' => $messagingConfig] + $otherConfig;
        $app->instance('config', new class($values)
        {
            /** @param  array<string, mixed>  $values */
            public function __construct(private array $values) {}

            public function get(string $key, mixed $default = null): mixed
            {
                if (array_key_exists($key, $this->values)) {
                    return $this->values[$key];
                }
                // Dotted lookup into the messaging array (e.g. capabilities-messaging.user_model).
                $cursor = $this->values;
                foreach (explode('.', $key) as $segment) {
                    if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                        return $default;
                    }
                    $cursor = $cursor[$segment];
                }

                return $cursor;
            }
        });
        $app->instance(CacheRepository::class, $cache ?? new Repository(new ArrayStore));
        (new MessagingServiceProvider($app))->register();

        return $app;
    }

    /**
     * @return array<string, mixed>
     */
    public static function telegramUpdate(
        string|int $chatId = 100,
        string|int $userId = 42,
        string $text = 'hello',
        string|int|null $topicId = null,
        int $updateId = 1,
    ): array {
        $message = [
            'message_id' => 7,
            'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'Test'],
            'chat' => ['id' => $chatId, 'type' => 'private'],
            'text' => $text,
        ];
        if ($topicId !== null) {
            $message['message_thread_id'] = $topicId;
        }

        return [
            'update_id' => $updateId,
            'message' => $message,
        ];
    }

    /**
     * Constructor dependency types of a class — structural proof of what it can call.
     *
     * @param  class-string  $class
     * @return list<string>
     */
    public static function constructorTypes(string $class): array
    {
        $params = (new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [];

        return array_map(static fn (\ReflectionParameter $p): string => (string) $p->getType()?->getName(), $params);
    }

    /**
     * Scan messaging package source for forbidden patterns (D-007).
     *
     * @return list<string>
     */
    public static function scanSource(string $pattern): array
    {
        $hits = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::MSG_SRC, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            if (preg_match($pattern, $contents)) {
                $hits[] = $file->getPathname();
            }
        }

        return $hits;
    }

    /**
     * @return list<string>
     */
    public static function allSourceContents(): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::MSG_SRC, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $out[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }

        return $out;
    }
}
