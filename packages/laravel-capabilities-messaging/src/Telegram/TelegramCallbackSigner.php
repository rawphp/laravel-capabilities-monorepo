<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

use RuntimeException;

/**
 * Signed short-lived approval callbacks for chat buttons (D-006).
 *
 * Payload: approval_id + action + exp + approver_hint + sig.
 * Never embeds capability input or bot token.
 */
final class TelegramCallbackSigner
{
    /** @var list<string> */
    public const ALLOWED_ACTIONS = ['accept', 'reject'];

    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds = 900,
    ) {
        if ($this->secret === '') {
            throw new RuntimeException('Callback signer secret must not be empty.');
        }
    }

    public function ttlSeconds(): int
    {
        return $this->ttlSeconds;
    }

    /**
     * Build a signed callback payload (no capability input).
     *
     * @return array{approval_id: string, action: string, exp: int, approver_hint: string, sig: string}
     */
    public function sign(
        string $approvalId,
        string $action,
        ?string $approverHint = null,
        ?int $now = null,
    ): array {
        $action = strtolower($action);
        if (! in_array($action, self::ALLOWED_ACTIONS, true)) {
            throw new RuntimeException(sprintf('Unsupported callback action "%s".', $action));
        }

        $now ??= time();
        $payload = [
            'approval_id' => $approvalId,
            'action' => $action,
            'exp' => $now + $this->ttlSeconds,
            'approver_hint' => $approverHint ?? '',
        ];
        $payload['sig'] = $this->hmac($payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload, ?int $now = null): bool
    {
        $now ??= time();

        $sig = $payload['sig'] ?? null;
        if (! is_string($sig) || $sig === '') {
            return false;
        }

        if (! isset($payload['approval_id'], $payload['action'], $payload['exp'])) {
            return false;
        }

        $action = strtolower((string) $payload['action']);
        if (! in_array($action, self::ALLOWED_ACTIONS, true)) {
            return false;
        }

        if ((int) $payload['exp'] < $now) {
            return false;
        }

        $expected = $this->hmac([
            'approval_id' => (string) $payload['approval_id'],
            'action' => $action,
            'exp' => (int) $payload['exp'],
            'approver_hint' => (string) ($payload['approver_hint'] ?? ''),
        ]);

        return hash_equals($expected, $sig);
    }

    /**
     * Compact token for Telegram callback_data size limits.
     *
     * @param  array{approval_id: string, action: string, exp: int, approver_hint: string, sig: string}  $payload
     */
    public function encode(array $payload): string
    {
        return rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decode(string $token): ?array
    {
        $pad = 4 - (strlen($token) % 4);
        if ($pad < 4) {
            $token .= str_repeat('=', $pad);
        }
        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Unsigned "approve id=5" alone is always refused.
     */
    public function rejectUnsignedApprovalId(string $approvalId): never
    {
        throw new RuntimeException(sprintf(
            'Unsigned forgeable approve id=%s alone is refused (D-006).',
            $approvalId,
        ));
    }

    /**
     * Ensure payload never carries capability input or secrets.
     *
     * @param  array<string, mixed>  $payload
     */
    public function assertSafePayload(array $payload): void
    {
        $forbidden = ['input', 'input_json', 'capability_input', 'bot_token', 'token', 'run_args'];
        foreach ($forbidden as $key) {
            if (array_key_exists($key, $payload)) {
                throw new RuntimeException(sprintf(
                    'Callback payload must not include "%s" (D-006).',
                    $key,
                ));
            }
        }
    }

    /**
     * @param  array{approval_id: string, action: string, exp: int, approver_hint?: string}  $payload
     */
    private function hmac(array $payload): string
    {
        $canonical = implode('|', [
            (string) $payload['approval_id'],
            (string) $payload['action'],
            (string) (int) $payload['exp'],
            (string) ($payload['approver_hint'] ?? ''),
        ]);

        return hash_hmac('sha256', $canonical, $this->secret);
    }
}
