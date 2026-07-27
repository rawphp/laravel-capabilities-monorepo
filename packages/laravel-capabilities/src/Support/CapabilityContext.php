<?php

namespace Rawphp\Capabilities\Support;

use InvalidArgumentException;

/**
 * Who/what invoked a capability — actor, caller, scope (CTX-001).
 *
 * Actor is always set after build: Authenticatable user or {@see SystemActor}.
 * Caller is server-derived (D-022), never a free-form client claim.
 */
final class CapabilityContext
{
    /** @var list<string> */
    public const CALLERS = ['agent', 'mcp', 'http', 'cli', 'job', 'artisan'];

    /**
     * @param  object  $actor  User / Authenticatable or SystemActor — never null
     * @param  array<string, mixed>|null  $agent
     * @param  array<string, mixed>|null  $mcp
     * @param  array<string, mixed>|null  $messaging
     * @param  array<string, mixed>|null  $job
     * @param  array<string, mixed>|null  $credential
     * @param  array<string, mixed>  $attributes  Trusted dispatcher-only attrs (not wire input)
     */
    public function __construct(
        private readonly string $caller,
        private readonly object $actor,
        private readonly ?CapabilityScope $scope = null,
        private readonly ?string $requestId = null,
        private readonly ?string $traceId = null,
        private readonly ?array $agent = null,
        private readonly ?array $mcp = null,
        private readonly ?array $messaging = null,
        private readonly ?array $job = null,
        private readonly ?array $credential = null,
        private readonly array $attributes = [],
    ) {
        if (! in_array($caller, self::CALLERS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid caller "%s"; expected one of: %s',
                $caller,
                implode(', ', self::CALLERS),
            ));
        }
    }

    /**
     * @param  array{
     *     caller: string,
     *     actor: object,
     *     scope?: CapabilityScope|null,
     *     request_id?: string|null,
     *     trace_id?: string|null,
     *     agent?: array<string, mixed>|null,
     *     mcp?: array<string, mixed>|null,
     *     messaging?: array<string, mixed>|null,
     *     job?: array<string, mixed>|null,
     *     credential?: array<string, mixed>|null,
     *     attributes?: array<string, mixed>
     * }  $fields
     */
    /** @var list<string> */
    public const MCP_AUTH_PROFILES = ['user_pat', 'integration', 'user_delegated'];

    public static function make(array $fields): self
    {
        if (! array_key_exists('actor', $fields) || $fields['actor'] === null) {
            throw new InvalidArgumentException('CapabilityContext requires a non-null actor principal.');
        }

        if (! array_key_exists('caller', $fields) || ! is_string($fields['caller']) || $fields['caller'] === '') {
            throw new InvalidArgumentException('CapabilityContext requires a non-empty caller.');
        }

        if (! is_object($fields['actor'])) {
            throw new InvalidArgumentException('CapabilityContext actor must be an object.');
        }

        $mcp = $fields['mcp'] ?? null;
        if (is_array($mcp) && array_key_exists('auth_profile', $mcp)) {
            $profile = $mcp['auth_profile'];
            if (! is_string($profile) || ! in_array($profile, self::MCP_AUTH_PROFILES, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid mcp auth_profile "%s"; expected one of: %s',
                    is_scalar($profile) ? (string) $profile : gettype($profile),
                    implode(', ', self::MCP_AUTH_PROFILES),
                ));
            }
        }

        return new self(
            caller: $fields['caller'],
            actor: $fields['actor'],
            scope: $fields['scope'] ?? null,
            requestId: $fields['request_id'] ?? null,
            traceId: $fields['trace_id'] ?? null,
            agent: $fields['agent'] ?? null,
            mcp: $mcp,
            messaging: $fields['messaging'] ?? null,
            job: $fields['job'] ?? null,
            credential: $fields['credential'] ?? null,
            attributes: $fields['attributes'] ?? [],
        );
    }

    public function caller(): string
    {
        return $this->caller;
    }

    public function actor(): object
    {
        return $this->actor;
    }

    /**
     * User when actor is not a SystemActor; null only for system principals.
     */
    public function user(): ?object
    {
        if ($this->actor instanceof SystemActor) {
            return null;
        }

        return $this->actor;
    }

    public function scope(): ?CapabilityScope
    {
        return $this->scope;
    }

    public function withScope(CapabilityScope $scope): self
    {
        return new self(
            caller: $this->caller,
            actor: $this->actor,
            scope: $scope,
            requestId: $this->requestId,
            traceId: $this->traceId,
            agent: $this->agent,
            mcp: $this->mcp,
            messaging: $this->messaging,
            job: $this->job,
            credential: $this->credential,
            attributes: $this->attributes,
        );
    }

    public function tenantId(): ?string
    {
        return $this->scope?->tenantId;
    }

    public function teamId(): ?string
    {
        return $this->scope?->teamId;
    }

    public function organizationId(): ?string
    {
        return $this->scope?->organizationId;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function traceId(): ?string
    {
        return $this->traceId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function agent(): ?array
    {
        return $this->agent;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function mcp(): ?array
    {
        return $this->mcp;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function messaging(): ?array
    {
        return $this->messaging;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function job(): ?array
    {
        return $this->job;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function credential(): ?array
    {
        return $this->credential;
    }

    /**
     * Trusted first-class job tenant (P2-005) — never wire/input magic keys.
     */
    public function jobTenantId(): ?string
    {
        $tenant = $this->job['tenant_id'] ?? null;

        return is_string($tenant) || is_int($tenant) ? (string) $tenant : null;
    }

    public function contextAttr(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
