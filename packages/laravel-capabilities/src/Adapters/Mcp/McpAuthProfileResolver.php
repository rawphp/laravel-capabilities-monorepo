<?php

namespace Rawphp\Capabilities\Adapters\Mcp;

use Rawphp\Capabilities\Support\SystemActor;

/**
 * Maps MCP credentials → actor + mcp context metadata (D-023).
 *
 * Profiles: user_pat | integration | user_delegated.
 * Tool JSON is never consulted here.
 */
final class McpAuthProfileResolver
{
    /** @var list<string> */
    public const PROFILES = ['user_pat', 'integration', 'user_delegated'];

    /**
     * @param  array{
     *     default_profile?: string,
     *     allow_integration_credentials?: bool,
     *     integration_actors?: array<string, string>,
     *     audit_client_id?: bool
     * }  $authConfig
     */
    public function __construct(
        private readonly array $authConfig = [
            'default_profile' => 'user_pat',
            'allow_integration_credentials' => false,
            'integration_actors' => [],
            'audit_client_id' => true,
        ],
    ) {}

    public function defaultProfile(): string
    {
        return (string) ($this->authConfig['default_profile'] ?? 'user_pat');
    }

    public function allowIntegrationCredentials(): bool
    {
        return (bool) ($this->authConfig['allow_integration_credentials'] ?? false);
    }

    public function auditClientId(): bool
    {
        return (bool) ($this->authConfig['audit_client_id'] ?? true);
    }

    /**
     * @return array{
     *     actor: object,
     *     mcp: array{
     *         auth_profile: string,
     *         client_id?: string|null,
     *         host?: string|null,
     *         session?: array<string, mixed>|null
     *     },
     *     tenant_id?: string|null
     * }
     */
    public function resolve(McpCredential $credential): array
    {
        $profile = $credential->authProfile;

        if (! in_array($profile, self::PROFILES, true)) {
            if (in_array($profile, ['user', 'token_user', 'oauth_user', ''], true) || $profile === 'token') {
                throw McpAuthException::vagueTokenUser();
            }
            throw McpAuthException::unknownProfile($profile);
        }

        return match ($profile) {
            'user_pat' => $this->resolveUserPat($credential),
            'integration' => $this->resolveIntegration($credential),
            'user_delegated' => $this->resolveDelegated($credential),
        };
    }

    /**
     * Whether a profile is recognized (without resolving a credential).
     */
    public function recognizes(string $profile): bool
    {
        return in_array($profile, self::PROFILES, true);
    }

    /**
     * @return array{actor: object, mcp: array<string, mixed>, tenant_id?: string|null}
     */
    private function resolveUserPat(McpCredential $credential): array
    {
        if ($credential->user === null) {
            throw McpAuthException::missingUser();
        }

        $mcp = [
            'auth_profile' => 'user_pat',
        ];
        if ($this->auditClientId() && $credential->clientId !== null) {
            $mcp['client_id'] = $credential->clientId;
        }

        return [
            'actor' => $credential->user,
            'mcp' => $mcp,
        ];
    }

    /**
     * @return array{actor: object, mcp: array<string, mixed>, tenant_id?: string|null}
     */
    private function resolveIntegration(McpCredential $credential): array
    {
        if (! $this->allowIntegrationCredentials()) {
            throw McpAuthException::integrationDisabled();
        }

        $clientId = $credential->clientId;
        if ($clientId === null || $clientId === '') {
            throw McpAuthException::missingClientId('integration');
        }

        $actors = $this->authConfig['integration_actors'] ?? [];
        if (! is_array($actors) || ! array_key_exists($clientId, $actors)) {
            throw McpAuthException::unknownIntegrationClient($clientId);
        }

        $systemName = (string) $actors[$clientId];
        $actor = SystemActor::named($systemName);

        $mcp = [
            'auth_profile' => 'integration',
            'client_id' => $clientId,
        ];

        // Trusted session/app config may supply tenant — never tool input (P2-005 / D-023).
        $tenantId = null;
        if (is_array($credential->session) && isset($credential->session['tenant_id'])) {
            $tenantId = (string) $credential->session['tenant_id'];
        }

        $result = [
            'actor' => $actor,
            'mcp' => $mcp,
        ];
        if ($tenantId !== null) {
            $result['tenant_id'] = $tenantId;
        }

        return $result;
    }

    /**
     * @return array{actor: object, mcp: array<string, mixed>, tenant_id?: string|null}
     */
    private function resolveDelegated(McpCredential $credential): array
    {
        if ($credential->user === null) {
            throw McpAuthException::missingUser();
        }

        $clientId = $credential->clientId;
        if ($clientId === null || $clientId === '') {
            throw McpAuthException::missingClientId('user_delegated');
        }

        $mcp = [
            'auth_profile' => 'user_delegated',
            'client_id' => $clientId,
        ];
        if ($credential->host !== null) {
            $mcp['host'] = $credential->host;
        }
        // Host session metadata is optional and non-authoritative for authz.
        if (is_array($credential->session)) {
            $mcp['session'] = $credential->session;
        }

        return [
            'actor' => $credential->user,
            'mcp' => $mcp,
        ];
    }
}
