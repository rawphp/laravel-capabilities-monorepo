<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesMessaging\Tests\Fixtures;

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Contracts\ApprovalGateway;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
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
        $adapter = $parts['adapter'] ?? new TelegramAdapter($bot);
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
