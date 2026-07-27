<?php

namespace Rawphp\Capabilities\Http;

/**
 * Derives caller from credential class — not client-spoofable headers (D-022).
 *
 * Controllers/adapters use this trait (or {@see CallerDeriver} directly) so
 * authorize / needsApproval / audit always see a server-derived caller.
 */
trait DetectsCaller
{
    protected function callerDeriver(?array $config = null): CallerDeriver
    {
        return new CallerDeriver($config ?? $this->callerConfig());
    }

    /**
     * @return array{
     *     token_abilities?: array<string, string>,
     *     oauth?: array<string, string>,
     *     privilege_order?: list<string>,
     *     reject_upgrade_attempts?: bool
     * }
     */
    protected function callerConfig(): array
    {
        return [
            'token_abilities' => ['capabilities:cli' => 'cli'],
            'oauth' => [],
            'privilege_order' => CallerDeriver::DEFAULT_PRIVILEGE_ORDER,
            'reject_upgrade_attempts' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $credential
     * @return array{caller: string, derived: string, rejected: bool, reason: ?string}
     */
    protected function detectCaller(array $credential = [], ?string $claimedHeader = null): array
    {
        return $this->callerDeriver()->resolve($credential, $claimedHeader);
    }
}
