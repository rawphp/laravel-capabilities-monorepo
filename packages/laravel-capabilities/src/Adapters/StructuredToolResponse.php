<?php

namespace Rawphp\Capabilities\Adapters;

use Rawphp\Capabilities\Support\CapabilityResult;

/**
 * Maps registry CapabilityResult → structured tool-handle payload for peers (AI-001 / MCP-001).
 *
 * Never invents a second mutation path — only shapes the wire/tool error object.
 */
final class StructuredToolResponse
{
    /**
     * @return array{
     *     ok: bool,
     *     data?: mixed,
     *     error?: array{code: string, message: string, structured: true, retryable?: bool},
     *     meta: array<string, mixed>
     * }
     */
    public static function fromResult(CapabilityResult $result, ?string $normalizeCode = null): array
    {
        if ($result->isOk()) {
            return [
                'ok' => true,
                'data' => $result->data,
                'meta' => $result->meta,
            ];
        }

        $code = $normalizeCode ?? (string) ($result->errorCode() ?? 'internal');
        $code = self::normalizeFailureCode($code);

        return [
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => (string) ($result->error['message'] ?? $code),
                'structured' => true,
                'retryable' => (bool) ($result->error['retryable'] ?? false),
                'details' => $result->error,
            ],
            'meta' => $result->meta,
        ];
    }

    /**
     * Alias codes used in AI/MCP failure-point matrices onto registry codes.
     */
    public static function normalizeFailureCode(string $code): string
    {
        return match ($code) {
            'validation_failed' => 'schema_invalid',
            'forbidden' => 'unauthorized',
            'capability_not_in_profile' => 'not_in_profile',
            'caller_claim_rejected', 'spoof_attempt' => 'caller_spoof_attempt',
            'actor_claim_rejected' => 'actor_spoof_attempt',
            'integration_not_allowed', 'integration_disabled' => 'integration_disabled',
            default => $code,
        };
    }
}
