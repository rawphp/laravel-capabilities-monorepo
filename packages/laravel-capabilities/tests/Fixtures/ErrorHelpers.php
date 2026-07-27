<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Http\CliJsonEnvelope;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\ErrorCodeMap;

/**
 * Helpers for D-018 error envelope unit matrices.
 */
final class ErrorHelpers
{
    public const CODES = [
        'validation_failed',
        'unauthenticated',
        'forbidden',
        'approval_required',
        'domain_error',
        'rate_limited',
        'conflict',
        'not_found',
        'output_invalid',
        'internal',
    ];

    public const CALLERS = ['agent', 'mcp', 'http', 'cli', 'job'];

    public const ENVELOPE_FIELDS = ['code', 'message', 'request_id', 'retryable'];

    /**
     * Build a stable failure envelope for a code (without full pipeline).
     */
    public static function failure(string $code, string $requestId = 'req-err-1'): CapabilityResult
    {
        if ($code === 'approval_required') {
            return CapabilityResult::approvalRequired('appr-1', 'needs approval', [
                'request_id' => $requestId,
            ]);
        }

        $extra = [
            'request_id' => $requestId,
        ];
        if ($code === 'validation_failed') {
            $extra['violations'] = [
                ['field' => 'customer_id', 'message' => 'must be integer'],
            ];
        }

        return CapabilityResult::failure($code, 'error: '.$code, $extra, [
            'request_id' => $requestId,
        ]);
    }

    /**
     * Success envelope with full meta (D-018).
     */
    public static function success(
        string $capability = 'create-invoice',
        string $requestId = 'req-ok-1',
        bool $replay = false,
    ): CapabilityResult {
        return CapabilityResult::ok(
            ['invoice_id' => 1],
            [
                'request_id' => $requestId,
                'capability' => $capability,
                'idempotent_replay' => $replay,
            ],
        );
    }

    public static function assertStructuredEnvelope(CapabilityResult $result, string $code): void
    {
        expect($result->isOk())->toBeFalse()
            ->and($result->error)->toBeArray()
            ->and($result->error['code'] ?? null)->toBe($code)
            ->and($result->error)->toHaveKeys(['code', 'message', 'request_id', 'retryable'])
            ->and(is_string($result->error['message'] ?? null))->toBeTrue()
            ->and(array_key_exists('retryable', $result->error))->toBeTrue()
            ->and(ErrorCodeMap::httpStatus($code))->toBe(ErrorCodeMap::httpStatus($code))
            ->and($result->error['http_status'] ?? ErrorCodeMap::httpStatus($code))
            ->toBe(ErrorCodeMap::httpStatus($code))
            ->and($result->error['cli_exit'] ?? ErrorCodeMap::cliExit($code))
            ->toBe(ErrorCodeMap::cliExit($code));
    }

    public static function assertNotUnstructured(CapabilityResult $result): void
    {
        $arr = $result->toArray();
        expect($arr)->toHaveKey('ok')
            ->and($arr['ok'])->toBeFalse()
            ->and($arr)->toHaveKey('error')
            ->and($arr['error'])->toBeArray()
            ->and($arr['error'])->toHaveKey('code');
    }

    public static function cliJson(CapabilityResult $result): array
    {
        return CliJsonEnvelope::fromResult($result);
    }
}
