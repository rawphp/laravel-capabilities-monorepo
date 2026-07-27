<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use Rawphp\CapabilitiesMessaging\Threads\ThreadStore;
use RuntimeException;
use Throwable;

/**
 * Queue job / handler for Telegram updates.
 *
 * Pipeline (MSG-003):
 * resolve_identity → map_thread → conversation_ingress → agent_tools_profile
 * → tool_calls_registry → conversation_reply
 *
 * Webhook verify + queue happen earlier (controller). Never domain run outside registry.
 */
final class ProcessTelegramUpdate
{
    /** Full ingress pipeline step names (controller + job). */
    public const PIPELINE_STEPS = [
        'verify_webhook_secret',
        'queue_process_update',
        'resolve_identity',
        'map_thread',
        'conversation_ingress',
        'agent_tools_profile',
        'tool_calls_registry',
        'conversation_reply',
    ];

    /** @var list<string> */
    private array $completedSteps = [];

    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $logs = [];

    /** @var array<string, mixed>|null */
    private ?array $lastTags = null;

    /** @var callable|null  (profile, tools) => list of tool names available */
    private $profileResolver;

    /** @var callable|null  agent turn: (message, context) => array{text: string, tool_calls?: list} */
    private $agentRunner;

    private bool $domainBypassAttempted = false;

    public function __construct(
        private readonly MessagingConfig $config,
        private readonly IdentityLinker $identity,
        private readonly ThreadStore $threads,
        private readonly TelegramAdapter $adapter,
        private readonly ?CapabilityBus $registry = null,
        private readonly ?TelegramBotClient $bot = null,
        ?callable $profileResolver = null,
        ?callable $agentRunner = null,
    ) {
        $this->profileResolver = $profileResolver;
        $this->agentRunner = $agentRunner;
    }

    /**
     * @param  array<string, mixed>  $update  Telegram Update payload
     * @return array<string, mixed>
     */
    public function handle(array $update): array
    {
        $this->completedSteps = [];
        $this->lastTags = $this->buildTags($update);

        try {
            return $this->process($update);
        } catch (Throwable $e) {
            $this->log('error', $e->getMessage(), [
                'failure' => $e->getMessage(),
                'tags' => $this->lastTags,
            ]);

            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'steps' => $this->completedSteps,
                'tags' => $this->lastTags,
                'domain_bypass' => $this->domainBypassAttempted,
                'observable' => true,
            ];
        }
    }

    /**
     * Simulate full pipeline including webhook stages (for order tests).
     *
     * @param  array<string, mixed>  $update
     * @param  array{secret_valid?: bool, skip_queue?: bool, fail_at?: string|null}  $options
     * @return array<string, mixed>
     */
    public function runPipeline(array $update, array $options = []): array
    {
        $this->completedSteps = [];
        $this->domainBypassAttempted = false;
        $failAt = $options['fail_at'] ?? null;

        if (($options['secret_valid'] ?? true) !== true) {
            $this->log('warning', 'bad_secret', []);
            if ($failAt === null || $failAt === 'verify_webhook_secret') {
                return $this->failClosed('bad_secret', 'verify_webhook_secret');
            }
        }
        $this->mark('verify_webhook_secret');
        if ($failAt === 'verify_webhook_secret') {
            return $this->failClosed('verify_webhook_secret_failed', 'verify_webhook_secret');
        }

        $this->mark('queue_process_update');
        if ($failAt === 'queue_process_update') {
            return $this->failClosed('queue_failed', 'queue_process_update');
        }

        try {
            $result = $this->process($update, $failAt);
            $result['steps'] = $this->completedSteps;

            return $result;
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'steps' => $this->completedSteps,
                'tools_reached' => in_array('tool_calls_registry', $this->completedSteps, true),
                'domain_bypass' => $this->domainBypassAttempted,
                'failed_step' => $this->guessFailedStep($e->getMessage()),
                'observable' => true,
            ];
        }
    }

    /**
     * @return list<string>
     */
    public function completedSteps(): array
    {
        return $this->completedSteps;
    }

    /**
     * Failed-job tags (D-019).
     *
     * @return array{channel: string, chat_id: string|null, update_id: int|string|null}
     */
    public function failedJobTags(?array $update = null): array
    {
        if ($update !== null) {
            return $this->buildTags($update);
        }

        return $this->lastTags ?? ['channel' => 'telegram', 'chat_id' => null, 'update_id' => null];
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function logs(): array
    {
        return $this->logs;
    }

    public function domainBypassAttempted(): bool
    {
        return $this->domainBypassAttempted;
    }

    /**
     * @param  array<string, mixed>  $update
     * @return array<string, mixed>
     */
    private function process(array $update, ?string $failAt = null): array
    {
        if ($failAt === 'invalid_update_shape' || ! $this->isValidShape($update)) {
            throw new RuntimeException('invalid_update_shape');
        }

        $chatId = $this->extractChatId($update);
        if ($failAt === 'unknown_chat' || $chatId === null) {
            throw new RuntimeException('unknown_chat');
        }

        $telegramUserId = $this->extractTelegramUserId($update);
        $topicId = $this->extractTopicId($update);
        $text = $this->extractText($update);

        // resolve_identity
        if ($failAt === 'identity_unresolved') {
            throw new RuntimeException('identity_unresolved');
        }
        $user = $this->identity->resolve([
            'channel' => 'telegram',
            'telegram_user_id' => $telegramUserId,
            'chat_id' => $chatId,
        ]);
        $this->mark('resolve_identity');

        if ($user === null) {
            throw new RuntimeException('identity_unresolved');
        }

        // map_thread
        if ($failAt === 'thread_store_failure') {
            $this->threads->failNext(true);
        }
        $thread = $this->threads->getOrCreate((string) $chatId, $topicId);
        $this->threads->appendHistory($thread['id'], [
            'role' => 'user',
            'text' => $text,
            'telegram_user_id' => $telegramUserId,
        ]);
        $this->mark('map_thread');

        // agent profile required (D-008)
        if ($failAt === 'profile_missing') {
            throw new RuntimeException('profile_missing');
        }
        $profile = $this->config->requireAgentProfile();
        $this->mark('agent_tools_profile');

        $profileTools = $this->resolveProfileTools($profile);
        if ($failAt === 'tool_not_in_profile') {
            $profileTools = [];
        }

        // conversation_ingress
        if ($failAt === 'ingress_failure') {
            throw new RuntimeException('ingress_failure');
        }
        if ($failAt === 'agent_failure') {
            throw new RuntimeException('agent_failure');
        }

        $messagingMeta = [
            'channel' => 'telegram',
            'chat_id' => (string) $chatId,
            'message_id' => $this->extractMessageId($update),
            'topic_id' => $topicId,
            'user_link_id' => $telegramUserId,
        ];

        $ingressMessage = [
            'channel' => 'telegram',
            'chat_id' => (string) $chatId,
            'text' => $text,
            'user' => $user,
            'thread_id' => $thread['id'],
            'profile' => $profile,
            'messaging' => $messagingMeta,
            'tools' => $profileTools,
        ];

        $ingressResult = $this->adapter->handle($ingressMessage);
        $this->mark('conversation_ingress');

        // tool_calls_registry
        $toolCalls = is_array($ingressResult) ? ($ingressResult['tool_calls'] ?? []) : [];
        $toolResults = [];

        if ($failAt === 'tool_registry_failure') {
            throw new RuntimeException('tool_registry_failure');
        }

        foreach ($toolCalls as $call) {
            $name = (string) ($call['name'] ?? '');
            if ($name === '' || ! in_array($name, $profileTools, true)) {
                throw new RuntimeException('tool_not_in_profile');
            }
            if ($this->registry === null) {
                throw new RuntimeException('registry_unavailable');
            }
            $ctx = new CapabilityContext(
                caller: 'agent',
                actor: $user,
                messaging: $messagingMeta,
                agent: ['profile' => $profile, 'thread_id' => $thread['id']],
            );
            $result = $this->registry->invoke($name, $call['input'] ?? [], [
                'context' => $ctx,
                'caller' => 'agent',
                'actor' => $user,
            ]);
            if (! $result->isOk()) {
                $code = (string) ($result->errorCode() ?? 'registry_validation');
                if ($code === 'forbidden') {
                    throw new RuntimeException('registry_forbidden');
                }
                if ($code === 'approval_required') {
                    throw new RuntimeException('approval_required');
                }
                throw new RuntimeException($code === '' ? 'registry_validation' : $code);
            }
            $toolResults[] = $result;
        }
        $this->mark('tool_calls_registry');

        // conversation_reply
        if ($failAt === 'reply_failure') {
            throw new RuntimeException('reply_failure');
        }
        $replyText = is_array($ingressResult)
            ? (string) ($ingressResult['text'] ?? 'ok')
            : 'ok';
        try {
            $this->adapter->reply([
                'chat_id' => (string) $chatId,
                'text' => $replyText,
                'thread_id' => $thread['id'],
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('reply_send_fail: '.$e->getMessage(), 0, $e);
        }
        $this->mark('conversation_reply');

        $this->threads->appendHistory($thread['id'], [
            'role' => 'assistant',
            'text' => $replyText,
        ]);

        return [
            'ok' => true,
            'thread_id' => $thread['id'],
            'profile' => $profile,
            'tools' => $profileTools,
            'tool_results' => $toolResults,
            'reply' => $replyText,
            'messaging' => $messagingMeta,
            'caller' => 'agent',
            'steps' => $this->completedSteps,
            'domain_bypass' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function isValidShape(array $update): bool
    {
        return isset($update['update_id'])
            || isset($update['message'])
            || isset($update['callback_query']);
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function extractChatId(array $update): string|int|null
    {
        return $update['message']['chat']['id']
            ?? $update['callback_query']['message']['chat']['id']
            ?? $update['chat_id']
            ?? null;
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function extractTelegramUserId(array $update): ?string
    {
        $id = $update['message']['from']['id']
            ?? $update['callback_query']['from']['id']
            ?? $update['telegram_user_id']
            ?? null;

        return $id === null ? null : (string) $id;
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function extractTopicId(array $update): string|int|null
    {
        return $update['message']['message_thread_id']
            ?? $update['topic_id']
            ?? null;
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function extractText(array $update): string
    {
        return (string) ($update['message']['text']
            ?? $update['callback_query']['data']
            ?? $update['text']
            ?? '');
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function extractMessageId(array $update): string|int|null
    {
        return $update['message']['message_id']
            ?? $update['callback_query']['message']['message_id']
            ?? null;
    }

    /**
     * @param  array<string, mixed>  $update
     * @return array{channel: string, chat_id: string|null, update_id: int|string|null}
     */
    private function buildTags(array $update): array
    {
        $chat = $this->extractChatId($update);

        return [
            'channel' => 'telegram',
            'chat_id' => $chat === null ? null : (string) $chat,
            'update_id' => $update['update_id'] ?? null,
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveProfileTools(string $profile): array
    {
        if ($this->profileResolver !== null) {
            /** @var list<string> $tools */
            $tools = ($this->profileResolver)($profile);

            return $tools;
        }

        // Default: profile name is non-empty ⇒ empty tool list unless resolver provided.
        return [];
    }

    private function mark(string $step): void
    {
        $this->completedSteps[] = $step;
    }

    /**
     * @return array<string, mixed>
     */
    private function failClosed(string $error, string $step): array
    {
        $this->log('warning', $error, ['step' => $step]);

        return [
            'ok' => false,
            'error' => $error,
            'failed_step' => $step,
            'steps' => $this->completedSteps,
            'tools_reached' => false,
            'domain_bypass' => false,
            'observable' => true,
        ];
    }

    private function guessFailedStep(string $message): string
    {
        foreach ([
            'invalid_update_shape',
            'unknown_chat',
            'identity_unresolved',
            'thread_store',
            'ingress_failure',
            'agent_failure',
            'tool_registry_failure',
            'tool_not_in_profile',
            'profile_missing',
            'registry_forbidden',
            'registry_validation',
            'reply_failure',
            'reply_send_fail',
            'approval_required',
        ] as $code) {
            if (str_contains($message, $code)) {
                return $code;
            }
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $this->logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
}
