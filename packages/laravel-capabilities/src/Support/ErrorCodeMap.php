<?php

namespace Rawphp\Capabilities\Support;

/**
 * Stable HTTP status + CLI exit mapping for capability error codes (D-018).
 */
final class ErrorCodeMap
{
    /**
     * @var array<string, array{http: int, cli_exit: int}>
     */
    private const MAP = [
        'validation_failed' => ['http' => 422, 'cli_exit' => 2],
        'unauthenticated' => ['http' => 401, 'cli_exit' => 3],
        'forbidden' => ['http' => 403, 'cli_exit' => 3],
        'approval_required' => ['http' => 202, 'cli_exit' => 4],
        'domain_error' => ['http' => 422, 'cli_exit' => 5],
        'rate_limited' => ['http' => 429, 'cli_exit' => 6],
        'conflict' => ['http' => 409, 'cli_exit' => 5],
        'not_found' => ['http' => 404, 'cli_exit' => 5],
        'output_invalid' => ['http' => 500, 'cli_exit' => 5],
        'internal' => ['http' => 500, 'cli_exit' => 1],
        'audit_failed' => ['http' => 500, 'cli_exit' => 1],
        'not_runnable' => ['http' => 500, 'cli_exit' => 1],
    ];

    public static function httpStatus(string $code): int
    {
        return self::MAP[$code]['http'] ?? 500;
    }

    public static function cliExit(string $code): int
    {
        return self::MAP[$code]['cli_exit'] ?? 1;
    }

    /**
     * @return array{http_status: int, cli_exit: int}
     */
    public static function wireFields(string $code): array
    {
        return [
            'http_status' => self::httpStatus($code),
            'cli_exit' => self::cliExit($code),
        ];
    }
}
