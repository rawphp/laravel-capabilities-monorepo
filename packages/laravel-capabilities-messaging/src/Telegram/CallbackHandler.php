<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use RuntimeException;

/**
 * Routes signed Telegram approval callbacks through ApprovalManager (D-006).
 *
 * Never executes domain run() itself — only accept/reject on the shared SM.
 */
final class CallbackHandler
{
    public function __construct(
        private readonly TelegramCallbackSigner $signer,
        private readonly IdentityLinker $identity,
        private readonly ?ApprovalManager $approvals = null,
    ) {}

    /**
     * @param  array<string, mixed>  $callbackPayload  decoded callback_data fields
     * @param  array<string, mixed>  $telegramUser     from callback_query.from
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

        if ($this->approvals === null) {
            throw new RuntimeException('ApprovalManager is required to process callbacks.');
        }

        $approvalId = (string) $callbackPayload['approval_id'];
        $row = $this->approvals->store()->find($approvalId);
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
        $result = $action === 'accept'
            ? $this->approvals->accept($approvalId, $user)
            : $this->approvals->reject($approvalId, $user);

        return [
            'status' => 'ok',
            'result' => $result,
            'message' => $action,
            'loaded_input_from_row' => true,
            'callback_had_input' => array_key_exists('input', $callbackPayload)
                || array_key_exists('input_json', $callbackPayload),
        ];
    }
}
