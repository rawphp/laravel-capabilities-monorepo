<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

use Rawphp\Capabilities\Contracts\ApprovalGateway;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use RuntimeException;

/**
 * Routes signed Telegram approval callbacks through {@see ApprovalGateway} (D-006).
 *
 * Never executes domain run() itself — only accept/reject on the shared SM via
 * the core port (not concrete ApprovalManager).
 *
 * A non-empty signed approver_hint binds the buttons to one product principal id:
 * a different linked user clicking a forwarded/leaked callback is forbidden.
 * An empty hint leaves the decision to the approval policy alone.
 */
final class CallbackHandler
{
    public function __construct(
        private readonly TelegramCallbackSigner $signer,
        private readonly IdentityLinker $identity,
        private readonly ?ApprovalGateway $approvals = null,
    ) {}

    /**
     * @param  array<string, mixed>  $callbackPayload  decoded callback_data fields
     * @param  array<string, mixed>  $telegramUser  from callback_query.from
     * @return array{status: string, result?: CapabilityResult|null, message: string}
     */
    public function handle(array $callbackPayload, array $telegramUser): array
    {
        if (isset($callbackPayload['approval_id']) && ! isset($callbackPayload['sig'])) {
            $this->signer->rejectUnsignedApprovalId((string) $callbackPayload['approval_id']);
        }

        $this->signer->assertSafePayload($callbackPayload);

        if (! $this->signer->verify($callbackPayload)) {
            return ['status' => 'invalid', 'message' => 'invalid_signature_or_expired'];
        }

        $action = strtolower((string) $callbackPayload['action']);
        if (! in_array($action, TelegramCallbackSigner::ALLOWED_ACTIONS, true)) {
            return ['status' => 'invalid', 'message' => 'unsupported_action'];
        }

        $telegramUserId = (string) ($telegramUser['id'] ?? $telegramUser['telegram_user_id'] ?? '');
        $user = $this->identity->resolve([
            'channel' => 'telegram',
            'telegram_user_id' => $telegramUserId,
        ]);

        if ($user === null) {
            return ['status' => 'forbidden', 'message' => 'unlinked_approver'];
        }

        $approverHint = (string) ($callbackPayload['approver_hint'] ?? '');
        if ($approverHint !== '' && $approverHint !== $this->principalId($user)) {
            return ['status' => 'forbidden', 'message' => 'approver_mismatch'];
        }

        if ($this->approvals === null) {
            throw new RuntimeException('ApprovalGateway is required to process callbacks.');
        }

        $approvalId = (string) $callbackPayload['approval_id'];
        // Use gateway find() so lazy TTL expiry matches HTTP/accept paths (D-006).
        $row = $this->approvals->find($approvalId);
        if ($row === null) {
            return ['status' => 'not_found', 'message' => 'unknown_approval'];
        }

        $status = (string) ($row['status'] ?? '');
        if (in_array($status, ['approved', 'rejected', 'expired', 'executed'], true)) {
            return ['status' => 'already_handled', 'message' => 'already_handled', 'result' => null];
        }

        if ($status !== 'pending') {
            return ['status' => 'already_handled', 'message' => 'already_handled', 'result' => null];
        }

        // Server loads input only from approval row — never from callback.
        // Audit which chat identity decided — the id that resolved the link.
        $options = ['decided_via' => ['channel' => 'telegram', 'channel_user_id' => $telegramUserId]];
        $result = $action === 'accept'
            ? $this->approvals->accept($approvalId, $user, $options)
            : $this->approvals->reject($approvalId, $user, null, $options);

        return [
            'status' => 'ok',
            'result' => $result,
            'message' => $action,
            'loaded_input_from_row' => true,
            'callback_had_input' => array_key_exists('input', $callbackPayload)
                || array_key_exists('input_json', $callbackPayload),
        ];
    }

    /**
     * Same id core records as decided_by: `id`, then getAuthIdentifier(); null fails closed.
     */
    private function principalId(object $user): ?string
    {
        if (isset($user->id)) {
            return (string) $user->id;
        }

        if (method_exists($user, 'getAuthIdentifier')) {
            return (string) $user->getAuthIdentifier();
        }

        return null;
    }
}
