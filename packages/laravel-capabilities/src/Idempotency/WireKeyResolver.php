<?php

namespace Rawphp\Capabilities\Idempotency;

/**
 * Resolve idempotency keys from surface wire formats (D-005).
 *
 * | Surface          | Source                                      |
 * |------------------|---------------------------------------------|
 * | HTTP             | Header wins over body `idempotency_key`     |
 * | CLI              | Always has a key (auto UUID if not set)     |
 * | MCP / AI agent   | Optional tool arg `idempotency_key`         |
 * | Job              | Optional `idempotencyKey` payload field     |
 * | Approval accept  | Header/field; default stored key on row     |
 */
final class WireKeyResolver
{
    /**
     * @param  array<string, mixed>  $headers  Case-insensitive header bag (or map)
     * @param  array<string, mixed>  $body  Request / tool / job payload
     * @param  string|null  $storedKey  Approval row key when accepting
     * @param  string|null  $configHeader  Configurable header name
     */
    public static function resolve(
        string $surface,
        array $headers = [],
        array $body = [],
        ?string $storedKey = null,
        ?string $configHeader = null,
        bool $cliAlwaysGenerate = true,
    ): ?string {
        $headerName = $configHeader ?? IdempotencyConfig::DEFAULT_HEADER;
        $fromHeader = self::headerValue($headers, $headerName);

        return match ($surface) {
            'http' => self::http($fromHeader, $body),
            'cli' => self::cli($fromHeader, $body, $cliAlwaysGenerate),
            'mcp', 'agent' => self::toolArg($fromHeader, $body),
            'job' => self::job($fromHeader, $body),
            'approval_accept' => self::approvalAccept($fromHeader, $body, $storedKey),
            default => self::http($fromHeader, $body),
        };
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function http(?string $header, array $body): ?string
    {
        if ($header !== null && $header !== '') {
            return $header;
        }

        return self::stringOrNull($body['idempotency_key'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function cli(?string $header, array $body, bool $alwaysGenerate = true): ?string
    {
        $manual = $header
            ?? self::stringOrNull($body['idempotency_key'] ?? null)
            ?? self::stringOrNull($body['idempotencyKey'] ?? null);

        if ($manual !== null && $manual !== '') {
            return $manual;
        }

        return $alwaysGenerate ? IdempotencyKey::generate() : null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function toolArg(?string $header, array $body): ?string
    {
        if ($header !== null && $header !== '') {
            return $header;
        }

        return self::stringOrNull($body['idempotency_key'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function job(?string $header, array $body): ?string
    {
        if ($header !== null && $header !== '') {
            return $header;
        }

        return self::stringOrNull($body['idempotencyKey'] ?? $body['idempotency_key'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function approvalAccept(?string $header, array $body, ?string $storedKey): ?string
    {
        $explicit = self::http($header, $body);
        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        return $storedKey !== null && $storedKey !== '' ? $storedKey : null;
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private static function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return self::stringOrNull($value);
            }
        }

        return null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value === '' ? null : $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }
}
