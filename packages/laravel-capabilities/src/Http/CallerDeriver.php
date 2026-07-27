<?php

namespace Rawphp\Capabilities\Http;

use Rawphp\Capabilities\Support\CapabilityContext;

/**
 * Server-derived caller from credential class / adapter code (D-022).
 *
 * Never trusts free-form client claims alone. Optional X-Capabilities-Caller
 * may only downgrade privilege, never upgrade.
 */
final class CallerDeriver
{
    /** Default privilege order: lower index = more privileged for approval gates. */
    public const DEFAULT_PRIVILEGE_ORDER = ['http', 'cli', 'mcp', 'agent', 'job'];

    /**
     * @param  array{
     *     token_abilities?: array<string, string>,
     *     oauth?: array<string, string>,
     *     privilege_order?: list<string>,
     *     reject_upgrade_attempts?: bool
     * }  $config
     */
    public function __construct(
        private readonly array $config = [],
    ) {}

    /**
     * Derive caller from credential / adapter facts only (no header).
     *
     * @param  array{
     *     source?: string|null,
     *     token_abilities?: list<string>|null,
     *     oauth_client_id?: string|null,
     *     oauth_client_type?: string|null,
     *     adapter?: string|null,
     *     server_caller?: string|null
     * }  $credential
     */
    public function deriveFromCredential(array $credential = []): string
    {
        // Explicit in-process adapter / server code wins (trusted process).
        if (isset($credential['server_caller']) && is_string($credential['server_caller'])) {
            return $this->normalizeCaller($credential['server_caller']) ?? 'http';
        }

        $adapter = $credential['adapter'] ?? $credential['source'] ?? null;
        if (is_string($adapter)) {
            $fromAdapter = match ($adapter) {
                'agent', 'laravel/ai', 'ai' => 'agent',
                'mcp', 'laravel/mcp' => 'mcp',
                'job', 'scheduler', 'queue' => 'job',
                'cli' => 'cli',
                'http', 'api' => 'http',
                'artisan' => 'artisan',
                default => null,
            };
            if ($fromAdapter !== null) {
                return $fromAdapter;
            }
        }

        $abilities = $credential['token_abilities'] ?? [];
        if (is_array($abilities) && $abilities !== []) {
            $map = $this->config['token_abilities'] ?? ['capabilities:cli' => 'cli'];
            foreach ($abilities as $ability) {
                if (! is_string($ability)) {
                    continue;
                }
                if (isset($map[$ability]) && is_string($map[$ability])) {
                    return $this->normalizeCaller($map[$ability]) ?? 'http';
                }
            }

            // Unmapped Sanctum PAT → http
            return 'http';
        }

        $oauthId = $credential['oauth_client_id'] ?? null;
        $oauthType = $credential['oauth_client_type'] ?? null;
        $oauthMap = $this->config['oauth'] ?? [];

        if (is_string($oauthId) && $oauthId !== '' && isset($oauthMap[$oauthId])) {
            return $this->normalizeCaller((string) $oauthMap[$oauthId]) ?? 'http';
        }

        if (is_string($oauthType) && $oauthType !== '') {
            $normalized = $this->normalizeCaller($oauthType);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        // Unregistered OAuth / bare request → http
        return 'http';
    }

    /**
     * Apply optional X-Capabilities-Caller claim: match, downgrade, or ignore upgrade.
     *
     * @return array{caller: string, rejected: bool, reason: ?string}
     */
    public function applyHeaderClaim(string $derived, ?string $claimedHeader): array
    {
        if ($claimedHeader === null || trim($claimedHeader) === '') {
            return ['caller' => $derived, 'rejected' => false, 'reason' => null];
        }

        $claimed = $this->normalizeCaller(trim($claimedHeader));
        if ($claimed === null) {
            // Unknown header value ignored
            return ['caller' => $derived, 'rejected' => false, 'reason' => 'unknown_header_ignored'];
        }

        if ($claimed === $derived) {
            return ['caller' => $derived, 'rejected' => false, 'reason' => null];
        }

        $order = $this->config['privilege_order'] ?? self::DEFAULT_PRIVILEGE_ORDER;
        $derivedIdx = array_search($derived, $order, true);
        $claimedIdx = array_search($claimed, $order, true);

        // Unknown relative privilege → ignore claim
        if ($derivedIdx === false || $claimedIdx === false) {
            return ['caller' => $derived, 'rejected' => false, 'reason' => 'unknown_relative_privilege'];
        }

        // Higher index = less privileged (stricter for approval). Downgrade allowed.
        if ($claimedIdx > $derivedIdx) {
            return ['caller' => $claimed, 'rejected' => false, 'reason' => 'downgrade'];
        }

        // Upgrade attempt (claimed more privileged than derived)
        $reject = (bool) ($this->config['reject_upgrade_attempts'] ?? false);
        if ($reject) {
            return [
                'caller' => $derived,
                'rejected' => true,
                'reason' => 'caller_claim_rejected',
            ];
        }

        return ['caller' => $derived, 'rejected' => false, 'reason' => 'upgrade_ignored'];
    }

    /**
     * Full resolve: credential derivation + optional header policy.
     *
     * Header alone (no credential facts) is never authoritative (D-022).
     *
     * @param  array<string, mixed>  $credential
     * @return array{caller: string, derived: string, rejected: bool, reason: ?string}
     */
    public function resolve(array $credential = [], ?string $claimedHeader = null): array
    {
        $hasCredential = $this->hasCredentialFacts($credential);
        $derived = $this->deriveFromCredential($credential);

        // Header alone cannot set or downgrade — no server credential/adapter fact.
        if (! $hasCredential) {
            return [
                'caller' => 'http',
                'derived' => 'http',
                'rejected' => false,
                'reason' => $claimedHeader !== null && trim((string) $claimedHeader) !== ''
                    ? 'header_alone_ignored'
                    : null,
            ];
        }

        $applied = $this->applyHeaderClaim($derived, $claimedHeader);

        return [
            'caller' => $applied['caller'],
            'derived' => $derived,
            'rejected' => $applied['rejected'],
            'reason' => $applied['reason'],
        ];
    }

    /**
     * @param  array<string, mixed>  $credential
     */
    private function hasCredentialFacts(array $credential): bool
    {
        if (isset($credential['server_caller']) || isset($credential['adapter']) || isset($credential['source'])) {
            return true;
        }
        if (! empty($credential['token_abilities']) && is_array($credential['token_abilities'])) {
            return true;
        }
        if (! empty($credential['oauth_client_id']) || ! empty($credential['oauth_client_type'])) {
            return true;
        }

        return false;
    }

    /**
     * Header alone (no credential) never sets caller — always http default + ignore claim authority.
     */
    public function fromHeaderAlone(?string $claimedHeader): string
    {
        // Without credential, only server may set caller; header alone is not authoritative.
        return 'http';
    }

    /**
     * Body / tool args never set caller.
     *
     * @param  array<string, mixed>  $body
     */
    public function fromClientBody(array $body): ?string
    {
        // Explicitly refuse client-supplied caller keys.
        return null;
    }

    public function isMorePrivileged(string $a, string $b): bool
    {
        $order = $this->config['privilege_order'] ?? self::DEFAULT_PRIVILEGE_ORDER;
        $ai = array_search($a, $order, true);
        $bi = array_search($b, $order, true);
        if ($ai === false || $bi === false) {
            return false;
        }

        return $ai < $bi;
    }

    private function normalizeCaller(string $value): ?string
    {
        $value = strtolower(trim($value));
        if (in_array($value, CapabilityContext::CALLERS, true)) {
            return $value;
        }

        return null;
    }
}
