<?php

namespace Rawphp\Capabilities\Adapters\Mcp;

/**
 * Server-derived MCP credential shape (never from tool JSON) (D-023).
 */
final class McpCredential
{
    /**
     * @param  object|null  $user  Product user for user_pat / user_delegated
     * @param  array<string, mixed>|null  $session  Trusted host session config (non-authoritative for authz)
     */
    public function __construct(
        public readonly string $authProfile,
        public readonly ?object $user = null,
        public readonly ?string $clientId = null,
        public readonly ?string $host = null,
        public readonly ?array $session = null,
    ) {}

    public static function userPat(object $user, ?string $clientId = null): self
    {
        return new self('user_pat', user: $user, clientId: $clientId);
    }

    public static function integration(string $clientId, ?array $session = null): self
    {
        return new self('integration', clientId: $clientId, session: $session);
    }

    public static function userDelegated(object $user, string $clientId, ?string $host = null, ?array $session = null): self
    {
        return new self('user_delegated', user: $user, clientId: $clientId, host: $host, session: $session);
    }
}
