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
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\UpdateQueue;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramCallbackSigner;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramWebhookController;
use Rawphp\CapabilitiesMessaging\Threads\ThreadStore;

/**
 * Builds messaging services from config without a Laravel container (unit-testable).
 */
final class MessagingBindings
{
    /**
     * @param  array<string, mixed>  $config
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
     *     aliases: array<string, class-string>
     * }
     */
    public static function build(array $config = [], string $appEnv = 'testing'): array
    {
        $cfg = MessagingConfig::fromArray($config, $appEnv);
        $bot = new FakeTelegramBotClient;
        $queue = new FakeQueue;
        $threads = new ThreadStore;
        $identity = new IdentityLinker($cfg);
        $secret = $cfg->webhookSecret() ?? 'deferred-unset';
        $signer = new TelegramCallbackSigner($secret, $cfg->callbackTtlSeconds());
        $adapter = new TelegramAdapter($bot);
        $notifier = new TelegramApprovalNotifier($cfg, $bot, $signer);
        $webhook = new TelegramWebhookController($cfg, $queue);
        $processor = new ProcessTelegramUpdate($cfg, $identity, $threads, $adapter, null, $bot);

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
                TelegramBotClient::class => FakeTelegramBotClient::class,
                UpdateQueue::class => FakeQueue::class,
            ],
        ];
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
            TelegramWebhookController::class,
            ProcessTelegramUpdate::class,
        ];
    }
}
