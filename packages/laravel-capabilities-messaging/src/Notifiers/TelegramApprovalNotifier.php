<?php

namespace Rawphp\CapabilitiesMessaging\Notifiers;

use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Support\Redactor;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotApiException;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\TelegramText;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramCallbackSigner;
use Throwable;

/**
 * Telegram channel approval notifier — implements core ApprovalNotifier (D-006 / D-007).
 *
 * Sends signed accept/reject buttons with a redacted view of the row's input, into the
 * originating forum topic. Never executes capabilities or domain services.
 * Bot API delivery failures are audited as `approval.notify_failed`, then rethrown (D-010).
 * telegram.enabled=false is a kill switch: nothing is sent, even with secrets set.
 */
final class TelegramApprovalNotifier implements ApprovalNotifier
{
    public function __construct(
        private readonly MessagingConfig $config,
        private readonly TelegramBotClient $bot,
        private readonly ?TelegramCallbackSigner $signer = null,
        private readonly ?AuditWriter $audit = null,
    ) {}

    /**
     * @param  array<string, mixed>  $approval
     */
    public function notifyPending(array $approval): void
    {
        if (! $this->config->telegramEnabled()) {
            return;
        }

        // Only requests that came from a chat have somewhere to put the buttons; HTTP / CLI
        // approvals are announced by their own channels (M-101 / D-006). Skipped before the
        // secret check so a Telegram misconfiguration never touches non-chat approvals (M-201).
        $chatId = $this->resolveChatId($approval);
        if ($chatId === null) {
            return;
        }

        $this->config->requireTelegramSecrets();

        $approvalId = (string) ($approval['id'] ?? '');
        if ($approvalId === '') {
            // Nothing to sign a button for; never executes the capability.
            return;
        }

        $signer = $this->signer ?? new TelegramCallbackSigner(
            $this->config->callbackSecret(),
            $this->config->callbackTtlSeconds(),
        );

        $accept = $signer->sign($approvalId, 'accept', (string) ($approval['approver_hint'] ?? ''));
        $reject = $signer->sign($approvalId, 'reject', (string) ($approval['approver_hint'] ?? ''));
        $signer->assertSafePayload($accept);
        $signer->assertSafePayload($reject);

        $params = [
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => 'Accept', 'callback_data' => $signer->encode($accept)],
                    ['text' => 'Reject', 'callback_data' => $signer->encode($reject)],
                ]],
            ],
        ];
        // Into the forum topic the request came from, not General (M-203).
        $topicId = $approval['messaging']['topic_id'] ?? null;
        if (is_numeric($topicId)) {
            $params['message_thread_id'] = (int) $topicId;
        }

        $text = $this->messageText($approval);

        $this->deliver($approval, 'sendMessage', fn (): array => $this->bot->sendMessage($chatId, $text, $params));
    }

    /**
     * What is being approved, from the row alone (M-203): the capability, an optional summary,
     * then the stored input with sensitive keys redacted, one `key: <json>` line each. The input
     * is the ground truth; the agent's chat reply describing it is LLM text. JSON-encoding every
     * value keeps a crafted string from faking extra lines.
     *
     * @param  array<string, mixed>  $approval
     */
    private function messageText(array $approval): string
    {
        $lines = ['Approval required: '.(string) ($approval['capability_name'] ?? 'capability')];

        $summary = trim((string) ($approval['summary'] ?? ''));
        if ($summary !== '') {
            $lines[] = $summary;
        }

        $input = $approval['input_json'] ?? null;
        if (is_string($input)) {
            $input = json_decode($input, true);
        }
        if (is_array($input)) {
            foreach (Redactor::redact($input) as $key => $value) {
                $label = (string) preg_replace('/[\x00-\x1F\x7F]/', ' ', (string) $key);
                $lines[] = $label.': '.json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            }
        }

        return TelegramText::truncate(implode("\n", $lines));
    }

    /**
     * Edit a previously sent approval message (e.g. mark expired).
     *
     * @param  array<string, mixed>  $approval
     */
    public function editMessage(array $approval, string $text): void
    {
        if (! $this->config->telegramEnabled()) {
            return;
        }

        $chatId = $this->resolveChatId($approval);
        $messageId = $approval['messaging']['message_id']
            ?? $approval['sent_message_id']
            ?? $approval['message_id']
            ?? null;

        if ($chatId === null || $messageId === null) {
            return;
        }

        $this->deliver($approval, 'editMessageText', fn (): array => $this->bot->editMessageText($chatId, $messageId, $text));
    }

    /**
     * Run one Bot API call; on failure leave an audit trace and rethrow.
     *
     * @param  array<string, mixed>  $approval
     * @param  callable(): array<string, mixed>  $call
     * @return array<string, mixed>
     */
    private function deliver(array $approval, string $method, callable $call): array
    {
        try {
            return $call();
        } catch (Throwable $e) {
            $this->auditDeliveryFailure($approval, $method, $e);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $approval
     */
    private function auditDeliveryFailure(array $approval, string $method, Throwable $e): void
    {
        if ($this->audit === null) {
            return;
        }

        $botApi = $e instanceof TelegramBotApiException ? $e : null;

        try {
            $this->audit->write([
                'event' => 'approval.notify_failed',
                'approval_id' => (string) ($approval['id'] ?? ''),
                'capability_name' => $approval['capability_name'] ?? null,
                'tenant_id' => $approval['tenant_id'] ?? null,
                'channel' => 'telegram',
                'method' => $method,
                'error_code' => $botApi !== null ? $botApi->errorCode : 'internal',
                'retryable' => $botApi !== null && $botApi->retryable,
                'error' => $e->getMessage(),
            ]);
        } catch (Throwable) {
            // best_effort (D-010): an audit failure must not mask the delivery failure.
        }
    }

    /**
     * @param  array<string, mixed>  $approval
     */
    private function resolveChatId(array $approval): ?string
    {
        $id = $approval['messaging']['chat_id']
            ?? $approval['chat_id']
            ?? null;

        return $id === null ? null : (string) $id;
    }
}
