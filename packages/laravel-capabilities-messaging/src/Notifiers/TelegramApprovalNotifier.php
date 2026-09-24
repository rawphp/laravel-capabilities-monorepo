<?php

namespace Rawphp\CapabilitiesMessaging\Notifiers;

use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotApiException;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramCallbackSigner;
use RuntimeException;
use Throwable;

/**
 * Telegram channel approval notifier — implements core ApprovalNotifier (D-006 / D-007).
 *
 * Sends signed accept/reject buttons. Never executes capabilities or domain services.
 * Bot API delivery failures are audited as `approval.notify_failed`, then rethrown (D-010).
 */
final class TelegramApprovalNotifier implements ApprovalNotifier
{
    /** @var list<array<string, mixed>> */
    private array $notified = [];

    /** @var list<array{approval: array<string, mixed>, text: string}> */
    private array $edits = [];

    private int $capabilityExecuteCount = 0;

    private int $domainServiceCalls = 0;

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
        $this->config->requireTelegramSecrets();

        $chatId = $this->resolveChatId($approval);
        if ($chatId === null) {
            throw new RuntimeException('Approval notify requires messaging.chat_id.');
        }

        $approvalId = (string) ($approval['id'] ?? '');
        if ($approvalId === '') {
            // Invalid id — still must not execute capability.
            $this->notified[] = $approval;

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

        $text = sprintf(
            "Approval required: %s\n%s",
            (string) ($approval['capability_name'] ?? 'capability'),
            (string) ($approval['summary'] ?? ''),
        );

        $result = $this->deliver($approval, 'sendMessage', fn (): array => $this->bot->sendMessage($chatId, $text, [
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => 'Accept', 'callback_data' => $signer->encode($accept)],
                    ['text' => 'Reject', 'callback_data' => $signer->encode($reject)],
                ]],
            ],
            'signed_buttons' => true,
            'accept_payload' => $accept,
            'reject_payload' => $reject,
        ]));

        $this->notified[] = array_merge($approval, [
            'sent_message_id' => $result['result']['message_id'] ?? null,
            'signed' => true,
        ]);
    }

    /**
     * Edit a previously sent approval message (e.g. mark expired).
     *
     * @param  array<string, mixed>  $approval
     */
    public function editMessage(array $approval, string $text): void
    {
        $chatId = $this->resolveChatId($approval);
        $messageId = $approval['messaging']['message_id']
            ?? $approval['sent_message_id']
            ?? $approval['message_id']
            ?? null;

        if ($chatId === null || $messageId === null) {
            $this->edits[] = ['approval' => $approval, 'text' => $text];

            return;
        }

        $this->deliver($approval, 'editMessageText', fn (): array => $this->bot->editMessageText($chatId, $messageId, $text));
        $this->edits[] = ['approval' => $approval, 'text' => $text];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function notified(): array
    {
        return $this->notified;
    }

    /**
     * @return list<array{approval: array<string, mixed>, text: string}>
     */
    public function edits(): array
    {
        return $this->edits;
    }

    public function capabilityExecuteCount(): int
    {
        return $this->capabilityExecuteCount;
    }

    public function domainServiceCalls(): int
    {
        return $this->domainServiceCalls;
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
