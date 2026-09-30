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
 *
 * A decoded callback_data token carries no approver_hint (64-byte limit): if it does not verify
 * unbound, it is re-verified with the clicking user's principal id as the hint, so a token bound
 * to someone else reads as invalid.
 */
final class CallbackHandler
{
    public function __construct(
        private readonly TelegramCallbackSigner $signer,
        private readonly IdentityLinker $identity,
        private readonly ?ApprovalGateway $approvals = null,
    ) {}

    /**
     * Decode a tapped button's `callback_data` token and route it (M-101). A token that does
     * not parse is `invalid` — the agent never sees it.
     *
     * @param  array<string, mixed>  $telegramUser  from callback_query.from
     * @return array{status: string, result?: CapabilityResult|null, message: string}
     */
    public function handleCallbackData(string $callbackData, array $telegramUser): array
    {
        $payload = $this->signer->decode($callbackData);
        if ($payload === null) {
            return ['status' => 'invalid', 'message' => 'malformed_callback_data'];
        }

        return $this->handle($payload, $telegramUser);
    }

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

        $invalid = ['status' => 'invalid', 'message' => 'invalid_signature_or_expired'];

        // Hint-less compact token that does not verify unbound may be bound to the clicking user.
        $boundToClicker = false;
        if (! $this->signer->verify($callbackPayload)) {
            if (array_key_exists('approver_hint', $callbackPayload)) {
                return $invalid;
            }
            $boundToClicker = true;
        }

        $action = strtolower((string) $callbackPayload['action']);
        if (! in_array($action, TelegramCallbackSigner::ALLOWED_ACTIONS, true)) {
            return ['status' => 'invalid', 'message' => 'unsupported_action'];
        }

        if ($this->approvals === null) {
            throw new RuntimeException('ApprovalGateway is required to process callbacks.');
        }

        $approvalId = (string) $callbackPayload['approval_id'];
        // Use gateway find() so lazy TTL expiry matches HTTP/accept paths (D-006).
        $row = $this->approvals->find($approvalId);

        // Resolve the link under the row's tenant: an approver linked in another
        // tenant resolves to no one (D-003), whatever the approval policy says.
        $telegramUserId = (string) ($telegramUser['id'] ?? $telegramUser['telegram_user_id'] ?? '');
        $user = $this->identity->resolve([
            'channel' => 'telegram',
            'telegram_user_id' => $telegramUserId,
            'expected_tenant_id' => $row['tenant_id'] ?? null,
        ]);

        if ($user === null) {
            return ['status' => 'forbidden', 'message' => 'unlinked_approver'];
        }

        if ($boundToClicker) {
            if (! $this->signer->verify($callbackPayload + ['approver_hint' => (string) $this->principalId($user)])) {
                return $invalid;
            }
        }

        $approverHint = (string) ($callbackPayload['approver_hint'] ?? '');
        if ($approverHint !== '' && $approverHint !== $this->principalId($user)) {
            return ['status' => 'forbidden', 'message' => 'approver_mismatch'];
        }

        // Identity is checked first so unlinked users cannot probe approval ids.
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

        if (! $this->decisionApplied($action, $result)) {
            $code = $result->errorCode() ?? 'failed';

            return ['status' => $this->failureStatus($code, $approvalId), 'result' => $result, 'message' => $code];
        }

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
     * Core reports a completed reject as the `rejected` failure; anything else not ok means the
     * tap did not do what its button says (M-202).
     */
    private function decisionApplied(string $action, CapabilityResult $result): bool
    {
        return $result->isOk() || ($action === 'reject' && $result->errorCode() === 'rejected');
    }

    /**
     * `forbidden` before the decision (approval policy) leaves the row pending; `forbidden` after
     * it (original actor no longer authorized at execution) is a failed run, not the tapper's fault.
     */
    private function failureStatus(string $code, string $approvalId): string
    {
        return match ($code) {
            'forbidden' => ($this->approvals?->find($approvalId)['status'] ?? null) === 'pending' ? 'forbidden' : 'failed',
            'conflict', 'expired' => 'already_handled',
            'not_found' => 'not_found',
            default => 'failed',
        };
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
