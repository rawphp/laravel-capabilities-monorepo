<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
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
 * When the agent asks for tools, their results go back to it as a follow-up ingress message
 * carrying `tool_results`, and its answer to them is the reply (one tool round per update).
 *
 * Webhook verify + queue happen earlier (controller). Never domain run outside registry.
 *
 * D-019: failures go to the optional PSR-3 logger with channel/chat/update tags.
 *
 * D-013: an optional core RateLimiter caps agent turns per chat_id per minute
 * (telegram.turns_per_minute), checked before identity so a flooding chat costs nothing.
 *
 * D-005: the agent turn and its tool invokes run at most once per update. A reply that fails to
 * send transiently is kept in the host cache ($pendingReplies) and the job fails; the retry only
 * re-sends it. Without that store the failure is terminal rather than a second turn.
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

    /** Seconds a reply waiting for a queue retry is kept (job backoff is 10s + 60s). */
    public const PENDING_REPLY_TTL = 3600;

    public const UNLINKED_REPLY = 'This Telegram account is not linked yet. Get a link code in the app and send /link <code> here.';

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
        private readonly ?Repository $pendingReplies = null,
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

        $topicId = TelegramUpdateParser::topicId($update);
        $pendingKey = $this->pendingReplyKey($update);
        $pending = $pendingKey === null ? null : $this->pendingReplies?->get($pendingKey);
        if (is_array($pending) && is_string($pending['text'] ?? null)) {
            return $this->resendPendingReply($pendingKey, (string) $chatId, $topicId, $pending);
        }

        $this->enforceChatTurnLimit((string) $chatId);

        $telegramUserId = TelegramUpdateParser::telegramUserId($update);
        $text = TelegramUpdateParser::text($update);

        $linkCode = $this->linkCode($text, $telegramUserId);
        if ($linkCode !== null) {
            return $this->bindLink((string) $chatId, $topicId, (string) $telegramUserId, $linkCode);
        }

        // resolve_identity
        $user = $this->identity->resolve([
            'channel' => 'telegram',
            'telegram_user_id' => $telegramUserId,
            'chat_id' => $chatId,
        ]);
        $this->mark('resolve_identity');

        if ($user === null) {
            $this->replyUnlinked($update, (string) $chatId, $topicId);

            throw new RuntimeException('identity_unresolved');
        }

        // map_thread — id only: nothing reads history back, so a long-lived worker keeps no
        // per-chat state (conversation memory belongs to the host AgentTurn).
        $threadId = $this->threads->threadIdFor((string) $chatId, $topicId);
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
            'thread_id' => $threadId,
            'profile' => $profile,
            'messaging' => $messagingMeta,
            'tools' => $profileTools,
        ];

        $answer = $this->adapter->handle($ingressMessage);
        $this->mark('conversation_ingress');

        // tool_calls_registry
        $toolCalls = is_array($answer) ? ($answer['tool_calls'] ?? []) : [];
        $toolResults = $this->invokeTools($toolCalls, $update, $user, $profile, $profileTools, $threadId, $messagingMeta);
        $this->mark('tool_calls_registry');

        // One tool round: the agent answers its tool results; tool calls in that answer are ignored.
        if ($toolResults !== []) {
            $answer = $this->adapter->handle($ingressMessage + ['tool_results' => $toolResults]);
        }

        // conversation_reply
        $replyText = is_array($answer)
            ? (string) ($answer['text'] ?? 'ok')
            : 'ok';

        // The reply is sent either way; ok reports whether the request itself went through.
        $last = $toolResults === [] ? null : $toolResults[array_key_last($toolResults)]['result'];
        $toolError = $last === null || $last->isOk() ? null : ($last->errorCode() ?? 'internal');

        $this->sendReply((string) $chatId, $topicId, $replyText, $pendingKey, $toolError);

        return [
            'ok' => $toolError === null,
            'error' => $toolError,
            'thread_id' => $threadId,
            'profile' => $profile,
            'tools' => $profileTools,
            'tool_results' => $toolResults,
            'reply' => $replyText,
            'messaging' => $messagingMeta,
            'caller' => 'agent',
            'steps' => $this->completedSteps,
            'tags' => $this->lastTags,
        ];
    }

    /**
     * Send the agent's reply. A transient Bot API failure keeps the reply for the queue retry
     * (when there is a store and an update key) and fails the job; anything else is terminal.
     */
    private function sendReply(string $chatId, string|int|null $topicId, string $text, ?string $pendingKey, ?string $toolError): void
    {
        try {
            $this->adapter->reply(['chat_id' => $chatId, 'topic_id' => $topicId, 'text' => $text]);
        } catch (Throwable $e) {
            $message = 'reply_send_fail: '.$e->getMessage();
            if ($e instanceof TelegramBotApiException && $e->retryable && $pendingKey !== null && $this->pendingReplies !== null) {
                $this->pendingReplies->put($pendingKey, ['text' => $text, 'error' => $toolError], self::PENDING_REPLY_TTL);

                throw new RetryableUpdateFailure($message, 0, $e);
            }
            throw new RuntimeException($message, 0, $e);
        }
        $this->mark('conversation_reply');
    }

    /**
     * Queue retry after a transient reply failure: send the kept reply only — no turn limit hit,
     * no identity, no agent turn, no tool invokes.
     *
     * @param  array<string, mixed>  $pending
     * @return array<string, mixed>
     */
    private function resendPendingReply(string $pendingKey, string $chatId, string|int|null $topicId, array $pending): array
    {
        try {
            $this->sendReply($chatId, $topicId, (string) $pending['text'], $pendingKey, $pending['error'] ?? null);
        } catch (Throwable $e) {
            if (! $e instanceof RetryableUpdateFailure) {
                $this->pendingReplies?->forget($pendingKey);
            }

            throw $e;
        }
        $this->pendingReplies?->forget($pendingKey);

        $error = is_string($pending['error'] ?? null) ? $pending['error'] : null;

        return [
            'ok' => $error === null,
            'error' => $error,
            'reply' => (string) $pending['text'],
            'steps' => $this->completedSteps,
            'tags' => $this->lastTags,
        ];
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function pendingReplyKey(array $update): ?string
    {
        $key = TelegramUpdateParser::updateKey($update);

        return $key === null ? null : 'capabilities-messaging:reply:'.$key;
    }

    /**
     * Invoke the agent's tool calls through the bus, in order, stopping after the first result
     * that is not ok. Every outcome (output, approval_required, refusal, transient failure) is
     * returned for the agent to answer; none fails the update.
     *
     * @param  array<int, mixed>  $toolCalls
     * @param  array<string, mixed>  $update
     * @param  list<string>  $profileTools
     * @param  array<string, mixed>  $messagingMeta
     * @return list<array{name: string, input: array<string, mixed>, result: CapabilityResult}>
     */
    private function invokeTools(
        array $toolCalls,
        array $update,
        object $user,
        string $profile,
        array $profileTools,
        string $threadId,
        array $messagingMeta,
    ): array {
        $toolResults = [];
        foreach ($toolCalls as $call) {
            $name = (string) ($call['name'] ?? '');
            if ($name === '' || ! in_array($name, $profileTools, true)) {
                throw new RuntimeException('tool_not_in_profile');
            }
            if ($this->registry === null) {
                throw new RuntimeException('registry_unavailable');
            }
            $input = (array) ($call['input'] ?? []);
            $options = [
                'context' => new CapabilityContext(
                    caller: 'agent',
                    actor: $user,
                    messaging: $messagingMeta,
                    agent: ['profile' => $profile, 'thread_id' => $threadId],
                ),
                'caller' => 'agent',
                'actor' => $user,
                'tool_profile' => $profile,
                // Core pipeline enforces the per-turn tool budget from this count (D-013).
                'agent_turn_tool_calls' => count($toolResults) + 1,
            ];
            // D-005: redelivered update → same key → store replay, not a second run().
            $key = TelegramUpdateParser::idempotencyKey($update, count($toolResults));
            if ($key !== null) {
                $options['idempotency_key'] = $key;
            }
            $result = $this->registry->invoke($name, $input, $options);
            $toolResults[] = ['name' => $name, 'input' => $input, 'result' => $result];

            if (! $result->isOk()) {
                $this->log('warning', 'Telegram tool call not ok: '.($result->errorCode() ?? 'unknown'), [
                    'failure' => $result->errorCode(),
                    'capability' => $name,
                    'tags' => $this->lastTags,
                ]);

                break;
            }
        }

        return $toolResults;
    }

    /**
     * code_link mode, private chat: tell an unlinked user how to link instead of staying silent.
     * Groups stay silent (every unlinked member would get a reply), and so does allowlist mode.
     *
     * @param  array<string, mixed>  $update
     */
    private function replyUnlinked(array $update, string $chatId, string|int|null $topicId): void
    {
        if ($this->config->identityMode() !== 'code_link' || ! TelegramUpdateParser::isPrivateChat($update)) {
            return;
        }

        $this->adapter->reply(['chat_id' => $chatId, 'topic_id' => $topicId, 'text' => self::UNLINKED_REPLY]);
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
    private function bindLink(string $chatId, string|int|null $topicId, string $telegramUserId, string $code): array
    {
        $linked = $this->identity->bindWithCode($telegramUserId, $code) !== null;
        $this->mark('link_identity');

        $this->adapter->reply([
            'chat_id' => $chatId,
            'topic_id' => $topicId,
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
