<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

use Psr\Log\LoggerInterface;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotApiException;
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
 *
 * D-019: failures go to the optional PSR-3 logger with channel/chat/update tags.
 *
 * D-013: an optional core RateLimiter caps agent turns per chat_id per minute
 * (telegram.turns_per_minute), checked before identity so a flooding chat costs nothing.
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

    public const LINKED_REPLY = 'Linked. You can chat with the assistant now.';

    public const LINK_FAILED_REPLY = 'That link code is invalid or expired. Ask for a new one in the app.';

    /** `/start <code>` or `/link <code>` — code_link bind step (MSG-002). */
    private const LINK_COMMAND = '#^/(?:start|link)(?:@\w+)?\s+([0-9a-f]{16})$#';

    /** @var list<string> */
    private array $completedSteps = [];

    /** @var array<string, mixed>|null */
    private ?array $lastTags = null;

    /** @var callable|null (profile) => list of tool names the profile exposes; none = no tools */
    private $profileResolver;

    public function __construct(
        private readonly MessagingConfig $config,
        private readonly IdentityLinker $identity,
        private readonly ThreadStore $threads,
        private readonly TelegramAdapter $adapter,
        private readonly ?CapabilityBus $registry = null,
        private readonly ?TelegramBotClient $bot = null,
        ?callable $profileResolver = null,
        private readonly ?RateLimiter $turnLimiter = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->profileResolver = $profileResolver;
    }

    /**
     * @param  array<string, mixed>  $update  Telegram Update payload
     * @return array<string, mixed>
     */
    public function handle(array $update): array
    {
        $this->completedSteps = [];
        $this->lastTags = TelegramUpdateParser::tags($update);

        try {
            return $this->process($update);
        } catch (Throwable $e) {
            // Expected per-user outcomes are warnings; anything else is an error (D-019).
            $expected = in_array($e->getMessage(), ['identity_unresolved', 'rate_limited'], true);
            $this->log($expected ? 'warning' : 'error', 'Telegram update failed: '.$e->getMessage(), [
                'failure' => $e->getMessage(),
                'tags' => $this->lastTags,
            ]);

            // Transient: fail the queued job so the queue retries (D-019 failed-job tags).
            if ($e instanceof RetryableUpdateFailure) {
                throw $e;
            }

            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'steps' => $this->completedSteps,
                'tags' => $this->lastTags,
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
            return TelegramUpdateParser::tags($update);
        }

        return $this->lastTags ?? ['channel' => 'telegram', 'chat_id' => null, 'update_id' => null];
    }

    /**
     * @param  array<string, mixed>  $update
     * @return array<string, mixed>
     */
    private function process(array $update): array
    {
        if (! TelegramUpdateParser::isValidShape($update)) {
            throw new RuntimeException('invalid_update_shape');
        }

        $chatId = TelegramUpdateParser::chatId($update);
        if ($chatId === null) {
            throw new RuntimeException('unknown_chat');
        }

        $this->enforceChatTurnLimit((string) $chatId);

        $telegramUserId = TelegramUpdateParser::telegramUserId($update);
        $topicId = TelegramUpdateParser::topicId($update);
        $text = TelegramUpdateParser::text($update);

        $linkCode = $this->linkCode($text, $telegramUserId);
        if ($linkCode !== null) {
            return $this->bindLink((string) $chatId, (string) $telegramUserId, $linkCode);
        }

        // resolve_identity
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
        $thread = $this->threads->getOrCreate((string) $chatId, $topicId);
        $this->threads->appendHistory($thread['id'], [
            'role' => 'user',
            'text' => $text,
            'telegram_user_id' => $telegramUserId,
        ]);
        $this->mark('map_thread');

        // agent profile required (D-008)
        $profile = $this->config->requireAgentProfile();
        $this->mark('agent_tools_profile');

        $profileTools = $this->resolveProfileTools($profile);

        // conversation_ingress

        $messagingMeta = [
            'channel' => 'telegram',
            'chat_id' => (string) $chatId,
            'message_id' => TelegramUpdateParser::messageId($update),
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

        $turnToolCalls = 0;
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
            $options = [
                'context' => $ctx,
                'caller' => 'agent',
                'actor' => $user,
                'tool_profile' => $profile,
                // Core pipeline enforces the per-turn tool budget from this count (D-013).
                'agent_turn_tool_calls' => ++$turnToolCalls,
            ];
            // D-005: redelivered update → same key → store replay, not a second run().
            // Index = count of prior successful calls (any failure throws before the next).
            $key = TelegramUpdateParser::idempotencyKey($update, count($toolResults));
            if ($key !== null) {
                $options['idempotency_key'] = $key;
            }
            $result = $this->registry->invoke($name, $call['input'] ?? [], $options);
            if (! $result->isOk()) {
                $code = (string) ($result->errorCode() ?? 'registry_validation');
                if ($result->isRetryable()) {
                    throw new RetryableUpdateFailure($code);
                }
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
            $message = 'reply_send_fail: '.$e->getMessage();
            if ($e instanceof TelegramBotApiException && $e->retryable) {
                throw new RetryableUpdateFailure($message, 0, $e);
            }
            throw new RuntimeException($message, 0, $e);
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
        ];
    }

    /**
     * Link code presented in chat, only in code_link mode (allowlist must not be bypassed).
     */
    private function linkCode(string $text, ?string $telegramUserId): ?string
    {
        if ($telegramUserId === null || $this->config->identityMode() !== 'code_link') {
            return null;
        }

        return preg_match(self::LINK_COMMAND, trim($text), $m) === 1 ? $m[1] : null;
    }

    /**
     * Bind the chat user with an app-issued code and confirm in chat — no agent turn, no tools.
     *
     * @return array<string, mixed>
     */
    private function bindLink(string $chatId, string $telegramUserId, string $code): array
    {
        $linked = $this->identity->bindWithCode($telegramUserId, $code) !== null;
        $this->mark('link_identity');

        $this->adapter->reply([
            'chat_id' => $chatId,
            'text' => $linked ? self::LINKED_REPLY : self::LINK_FAILED_REPLY,
        ]);
        $this->mark('conversation_reply');

        if (! $linked) {
            $this->log('warning', 'Telegram link code refused', ['failure' => 'link_code_invalid', 'tags' => $this->lastTags]);
        }

        return [
            'ok' => $linked,
            'linked' => $linked,
            'error' => $linked ? null : 'link_code_invalid',
            'steps' => $this->completedSteps,
        ];
    }

    /**
     * D-013: cap agent turns per chat per minute, separate from the in-turn tool budget.
     */
    private function enforceChatTurnLimit(string $chatId): void
    {
        $max = $this->config->turnsPerMinute();
        if ($this->turnLimiter === null || $max <= 0) {
            return;
        }

        $key = 'rl:telegram:chat:'.$chatId;
        if ($this->turnLimiter->tooManyAttempts($key, $max)) {
            throw new RuntimeException('rate_limited');
        }
        $this->turnLimiter->hit($key, 60);
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

        // Fail closed: no resolver (no AgentTurn bound) ⇒ no tools.
        return [];
    }

    private function mark(string $step): void
    {
        $this->completedSteps[] = $step;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $this->logger?->log($level, $message, $context);
    }
}
