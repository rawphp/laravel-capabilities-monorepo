<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesMessaging\Tests\Fixtures;

use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\Support\FakeQueue;
use Rawphp\CapabilitiesMessaging\Support\FakeTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramWebhookController;
use Rawphp\CapabilitiesMessaging\Threads\ThreadStore;
use RuntimeException;

/**
 * Drives one Telegram update through the wired path — TelegramWebhookController (secret check)
 * → FakeQueue → ProcessTelegramUpdate — with a failure injected by a test fake, never by a
 * production flag. Linked user: Telegram 42 → u1; profile tools: support.ping.
 */
final class PipelineScenario
{
    /** Pipeline step → failure that breaks it. */
    public const STEP_FAILURES = [
        'verify_webhook_secret' => 'bad_secret',
        'queue_process_update' => 'queue_failed',
        'resolve_identity' => 'identity_unresolved',
        'map_thread' => 'thread_store_failure',
        'conversation_ingress' => 'ingress_failure',
        'agent_tools_profile' => 'profile_missing',
        // A tool outcome (even a failure) is answered by the agent; an out-of-profile call breaks the step.
        'tool_calls_registry' => 'tool_not_in_profile',
        'conversation_reply' => 'reply_failure',
    ];

    /**
     * @param  array<string, mixed>  $update
     */
    private function __construct(
        public readonly ProcessTelegramUpdate $processor,
        public readonly FakeCapabilityBus $registry,
        public readonly FakeTelegramBotClient $bot,
        private readonly array $update,
        private readonly bool $secretValid,
        private readonly bool $queueFails,
    ) {}

    public static function happy(): self
    {
        return self::failingAt('none');
    }

    public static function failingAt(string $failure): self
    {
        $registry = new FakeCapabilityBus;
        $bot = new FakeTelegramBotClient;
        $configOverrides = [];
        $threads = new ThreadStore;
        $agent = MessagingHelpers::echoAgent();
        $update = MessagingHelpers::telegramUpdate(userId: 42);
        $toolCall = static fn (string $name): callable => static fn (): array => [
            'text' => 'done',
            'tool_calls' => [['name' => $name, 'input' => []]],
        ];

        switch ($failure) {
            case 'invalid_update_shape':
                $update = ['garbage' => true];
                break;
            case 'unknown_chat':
                $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
                break;
            case 'identity_unresolved':
            case 'unlinked_user':
                $update = MessagingHelpers::telegramUpdate(userId: 999);
                break;
            case 'thread_store_failure':
                $threads = new class extends ThreadStore
                {
                    public function threadIdFor(string $chatId, string|int|null $topicId = null): string
                    {
                        throw new RuntimeException('thread_store_failure');
                    }
                };
                break;
            case 'profile_missing':
                $configOverrides = ['agent_profile' => ''];
                break;
            case 'ingress_failure':
            case 'agent_failure':
                $agent = static fn (): array => throw new RuntimeException($failure);
                break;
            case 'tool_not_in_profile':
                $agent = $toolCall('not.in.profile');
                break;
            case 'tool_registry_failure':
            case 'registry_forbidden':
            case 'registry_validation':
            case 'approval_required':
                $agent = $toolCall('support.ping');
                $registry->alwaysFail(match ($failure) {
                    'tool_registry_failure' => 'domain_error',
                    'registry_forbidden' => 'forbidden',
                    'registry_validation' => 'validation_failed',
                    default => 'approval_required',
                });
                break;
            case 'reply_failure':
            case 'reply_send_fail':
                $bot->failNextSend();
                break;
        }

        $config = MessagingHelpers::config($configOverrides);
        $identity = new IdentityLinker($config);
        $identity->link('42', 'u1');

        $processor = MessagingHelpers::processor([
            'config' => $config,
            'identity' => $identity,
            'threads' => $threads,
            'adapter' => new TelegramAdapter($bot, $agent),
            'registry' => $registry,
            'bot' => $bot,
            'profile_tools' => ['support.ping'],
        ]);

        return new self(
            $processor,
            $registry,
            $bot,
            $update,
            secretValid: $failure !== 'bad_secret',
            queueFails: $failure === 'queue_failed',
        );
    }

    /**
     * @return array<string, mixed> processor result plus webhook steps, tools_reached and failed_step
     */
    public function run(): array
    {
        $queue = new FakeQueue;
        if ($this->queueFails) {
            $queue->failNextPush();
        }
        $webhook = new TelegramWebhookController(MessagingHelpers::config(), $queue);
        $secret = $this->secretValid ? 'test-webhook-secret' : 'wrong-secret';

        $edge = $webhook->handle([TelegramWebhookController::SECRET_HEADER => $secret], $this->update);
        if (! $edge['queued']) {
            // 401 bad secret, 400 invalid update shape (same rule as the processor), 500 queue push failed.
            return [
                'ok' => false,
                'error' => $edge['error'] ?? 'not_queued',
                'failed_step' => match ($edge['status']) {
                    400 => 'invalid_update_shape',
                    500 => 'queue_process_update',
                    default => 'verify_webhook_secret',
                },
                'steps' => $edge['status'] === 401 ? [] : ['verify_webhook_secret'],
                'tools_reached' => false,
            ];
        }

        $result = $this->processor->handle($queue->pushed()[0]['payload']['update']);
        $result['steps'] = array_merge(['verify_webhook_secret', 'queue_process_update'], $result['steps'] ?? []);
        $result['tools_reached'] = in_array('tool_calls_registry', $result['steps'], true);
        if (! $result['ok']) {
            $result['failed_step'] = $result['error'];
        }

        return $result;
    }
}
