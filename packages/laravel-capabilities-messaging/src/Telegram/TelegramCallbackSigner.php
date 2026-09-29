<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

use RuntimeException;

/**
 * Signed short-lived approval callbacks for chat buttons (D-006).
 *
 * Signature covers approval_id + action + exp + approver_hint.
 * Never embeds capability input or bot token.
 *
 * Wire token (callback_data, <= 64 bytes): `{a|r}.{approval_id}.{exp base36}.{sig}` where sig is
 * the HMAC-SHA256 truncated to 96 bits (16 base64url chars). The approver hint is bound into the
 * signature but not transmitted: a decoded token verifies either unbound (empty hint) or once the
 * clicking user's principal id is supplied as the hint.
 */
final class TelegramCallbackSigner
{
    /** @var list<string> */
    public const ALLOWED_ACTIONS = ['accept', 'reject'];

    /** Telegram Bot API limit for inline button callback_data. */
    public const MAX_CALLBACK_DATA_BYTES = 64;

    private const SIG_LENGTH = 16;

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
     * Compact callback_data token (approver hint is signed, not transmitted).
     *
     * @param  array{approval_id: string, action: string, exp: int, approver_hint?: string, sig: string}  $payload
     *
     * @throws RuntimeException when the token would exceed Telegram's 64-byte callback_data limit
     */
    public function encode(array $payload): string
    {
        $token = implode('.', [
            $payload['action'] === 'reject' ? 'r' : 'a',
            (string) $payload['approval_id'],
            base_convert((string) (int) $payload['exp'], 10, 36),
            (string) $payload['sig'],
        ]);

        if (strlen($token) > self::MAX_CALLBACK_DATA_BYTES) {
            throw new RuntimeException(sprintf(
                'Callback token for approval "%s" is %d bytes; Telegram callback_data allows %d (D-006).',
                (string) $payload['approval_id'],
                strlen($token),
                self::MAX_CALLBACK_DATA_BYTES,
            ));
        }

        return $token;
    }

    /**
     * Parse a callback_data token. The result carries no approver_hint (see class doc).
     *
     * @return array{approval_id: string, action: string, exp: int, sig: string}|null
     */
    public function decode(string $token): ?array
    {
        if (preg_match('/^([ar])\.(.+)\.([0-9a-z]{1,13})\.([A-Za-z0-9_-]{'.self::SIG_LENGTH.'})$/', $token, $m) !== 1) {
            return null;
        }

        return [
            'approval_id' => $m[2],
            'action' => $m[1] === 'r' ? 'reject' : 'accept',
            'exp' => (int) base_convert($m[3], 36, 10),
            'sig' => $m[4],
        ];
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

        $mac = rtrim(strtr(base64_encode(hash_hmac('sha256', $canonical, $this->secret, true)), '+/', '-_'), '=');

        return substr($mac, 0, self::SIG_LENGTH);
    }
}
