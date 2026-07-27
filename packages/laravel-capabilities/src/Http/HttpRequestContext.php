<?php

namespace Rawphp\Capabilities\Http;

/**
 * Unit-testable HTTP request DTO — no Illuminate kernel / full request lifecycle.
 *
 * Controllers map real Illuminate requests into this shape at the adapter edge.
 */
final class HttpRequestContext
{
    /**
     * @param  array<string, string>  $headers  Lowercase header names
     * @param  array<string, mixed>|null  $jsonBody  Parsed JSON body (null if empty/not JSON)
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $credential  Server-derived credential facts for D-022
     */
    public function __construct(
        public readonly bool $authenticated = false,
        public readonly ?object $user = null,
        public readonly array $headers = [],
        public readonly ?array $jsonBody = null,
        public readonly ?string $rawBody = null,
        public readonly bool $malformedJson = false,
        public readonly array $query = [],
        public readonly array $credential = [],
        public readonly string $method = 'GET',
        public readonly string $path = '',
        public readonly ?string $authKind = null,
    ) {}

    public function header(string $name, ?string $default = null): ?string
    {
        $key = strtolower($name);

        return $this->headers[$key] ?? $default;
    }

    public function accept(): ?string
    {
        return $this->header('accept');
    }

    public function wantsCliEnvelope(): bool
    {
        $accept = $this->accept() ?? '';

        return str_contains($accept, 'application/vnd.capabilities.cli+json');
    }

    public function idempotencyKey(?string $headerName = 'Idempotency-Key'): ?string
    {
        $key = $this->header(strtolower($headerName ?? 'idempotency-key'));
        if ($key === null || $key === '') {
            return null;
        }

        return $key;
    }

    public function claimedCallerHeader(): ?string
    {
        return $this->header('x-capabilities-caller');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        return new self(
            authenticated: $overrides['authenticated'] ?? $this->authenticated,
            user: array_key_exists('user', $overrides) ? $overrides['user'] : $this->user,
            headers: $overrides['headers'] ?? $this->headers,
            jsonBody: array_key_exists('jsonBody', $overrides) ? $overrides['jsonBody'] : $this->jsonBody,
            rawBody: array_key_exists('rawBody', $overrides) ? $overrides['rawBody'] : $this->rawBody,
            malformedJson: $overrides['malformedJson'] ?? $this->malformedJson,
            query: $overrides['query'] ?? $this->query,
            credential: $overrides['credential'] ?? $this->credential,
            method: $overrides['method'] ?? $this->method,
            path: $overrides['path'] ?? $this->path,
            authKind: array_key_exists('authKind', $overrides) ? $overrides['authKind'] : $this->authKind,
        );
    }
}
