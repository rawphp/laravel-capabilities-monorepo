<?php

namespace Rawphp\Capabilities\Support;

/**
 * Stable HTTP status + CLI exit + retryable defaults for capability error codes (D-018).
 */
final class ErrorCodeMap
{
    /**
     * @var array<string, array{http: int, cli_exit: int, retryable: bool}>
     */
    private const MAP = [
        'validation_failed' => ['http' => 422, 'cli_exit' => 2, 'retryable' => false],
        'unauthenticated' => ['http' => 401, 'cli_exit' => 3, 'retryable' => false],
        'forbidden' => ['http' => 403, 'cli_exit' => 3, 'retryable' => false],
        'approval_required' => ['http' => 202, 'cli_exit' => 4, 'retryable' => false],
        'domain_error' => ['http' => 422, 'cli_exit' => 5, 'retryable' => false],
        'rate_limited' => ['http' => 429, 'cli_exit' => 6, 'retryable' => true],
        'conflict' => ['http' => 409, 'cli_exit' => 5, 'retryable' => false],
        'not_found' => ['http' => 404, 'cli_exit' => 5, 'retryable' => false],
        'not_configured' => ['http' => 501, 'cli_exit' => 5, 'retryable' => false],
        'output_invalid' => ['http' => 500, 'cli_exit' => 5, 'retryable' => false],
        'internal' => ['http' => 500, 'cli_exit' => 1, 'retryable' => true],
        'audit_failed' => ['http' => 500, 'cli_exit' => 1, 'retryable' => false],
        'not_runnable' => ['http' => 500, 'cli_exit' => 1, 'retryable' => false],
        'gone' => ['http' => 410, 'cli_exit' => 5, 'retryable' => false],
        'capability_not_in_profile' => ['http' => 403, 'cli_exit' => 3, 'retryable' => false],
        'expired' => ['http' => 410, 'cli_exit' => 5, 'retryable' => false],
    ];

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::MAP);
    }

    public static function httpStatus(string $code): int
    {
        return self::MAP[$code]['http'] ?? 500;
    }

    public static function cliExit(string $code): int
    {
        return self::MAP[$code]['cli_exit'] ?? 1;
    }

    public static function retryableDefault(string $code): bool
    {
        return self::MAP[$code]['retryable'] ?? false;
    }

    /**
     * Auth / profile / runnability refuses — terminal for accept mapping (not retryable limbo).
     *
     * @var list<string>
     */
    private const HARD_REFUSE_CODES = [
        'forbidden',
        'capability_not_in_profile',
        'not_runnable',
        'unauthenticated',
    ];

    public static function isHardRefuse(string $code): bool
    {
        return in_array($code, self::HARD_REFUSE_CODES, true);
    }

    public static function isKnown(string $code): bool
    {
        return isset(self::MAP[$code]);
    }

    /**
     * @return array{http_status: int, cli_exit: int, retryable: bool}
     */
    public static function wireFields(string $code): array
    {
        return [
            'http_status' => self::httpStatus($code),
            'cli_exit' => self::cliExit($code),
            'retryable' => self::retryableDefault($code),
        ];
    }
}
