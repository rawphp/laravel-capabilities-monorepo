<?php

namespace Rawphp\Capabilities\Adapters\Mcp;

use RuntimeException;

/**
 * MCP credential / auth-profile resolution failures (D-023).
 */
final class McpAuthException extends RuntimeException
{
    public static function vagueTokenUser(): self
    {
        return new self('MCP credentials must declare an explicit auth profile (user_pat|integration|user_delegated); vague token user is refused (D-023).');
    }

    public static function integrationDisabled(): self
    {
        return new self('Integration client credentials are disabled (surfaces.mcp.auth.allow_integration_credentials=false) (D-023).');
    }

    public static function missingUser(): self
    {
        return new self('MCP auth profile requires a bound product user principal (D-023).');
    }

    public static function missingClientId(string $profile): self
    {
        return new self(sprintf('MCP auth profile "%s" requires client_id (D-023).', $profile));
    }

    public static function unknownIntegrationClient(string $clientId): self
    {
        return new self(sprintf('MCP integration client "%s" is not registered in integration_actors (D-023).', $clientId));
    }

    public static function unknownProfile(string $profile): self
    {
        return new self(sprintf('Unknown MCP auth profile "%s" (D-023).', $profile));
    }
}
