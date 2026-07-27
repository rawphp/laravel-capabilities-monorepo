<?php

namespace Rawphp\Capabilities\Approval;

/**
 * Verifies short-lived signed approval callbacks (chat / HTTP button payloads) — D-006.
 *
 * Core has no Telegram Bot SDK; this only validates HMAC tokens over
 * approval_id + action + exp + approver_hint.
 */
final class ApprovalCallbackVerifier
{
    public function __construct(
        private readonly string $secret,
        private readonly int $defaultTtlSeconds = 900,
    ) {}

    /**
     * @param  array{approval_id: string, action: string, exp: int, approver_hint?: string}  $payload
     */
    public function sign(array $payload): string
    {
        $canonical = $this->canonical($payload);

        return hash_hmac('sha256', $canonical, $this->secret);
    }

    /**
     * @param  array{approval_id?: string, action?: string, exp?: int, approver_hint?: string, sig?: string}  $payload
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

        $exp = (int) $payload['exp'];
        if ($exp < $now) {
            return false;
        }

        $expected = $this->sign([
            'approval_id' => (string) $payload['approval_id'],
            'action' => (string) $payload['action'],
            'exp' => $exp,
            'approver_hint' => (string) ($payload['approver_hint'] ?? ''),
        ]);

        return hash_equals($expected, $sig);
    }

    /**
     * Unsigned "approve id=5" alone is always refused.
     */
    public function acceptUnsignedIdOnly(string $approvalId): never
    {
        throw new \RuntimeException(sprintf(
            'Unsigned forgeable approve id=%s alone is refused (D-006).',
            $approvalId,
        ));
    }

    /**
     * @param  array{approval_id: string, action: string, exp: int, approver_hint?: string}  $payload
     */
    private function canonical(array $payload): string
    {
        return implode('|', [
            (string) $payload['approval_id'],
            (string) $payload['action'],
            (string) (int) $payload['exp'],
            (string) ($payload['approver_hint'] ?? ''),
        ]);
    }
}
