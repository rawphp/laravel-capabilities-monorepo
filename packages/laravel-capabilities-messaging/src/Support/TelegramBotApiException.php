<?php

namespace Rawphp\CapabilitiesMessaging\Support;

use Rawphp\Capabilities\Support\ErrorCodeMap;
use RuntimeException;

/**
 * Outbound Bot API failure, classified with the same D-018 error codes as the capability API.
 */
final class TelegramBotApiException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly string $method,
        public readonly string $errorCode,
        public readonly bool $retryable,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $response  decoded Bot API body with ok !== true
     */
    public static function fromResponse(string $method, array $response): self
    {
        $status = is_int($response['error_code'] ?? null) ? $response['error_code'] : 0;
        $code = match (true) {
            $status === 400 => 'validation_failed',
            $status === 401 => 'unauthenticated',
            $status === 403 => 'forbidden',
            $status === 404 => 'not_found',
            $status === 409 => 'conflict',
            $status === 429 => 'rate_limited',
            $status >= 400 && $status < 500 => 'domain_error',
            default => 'internal',
        };
        $description = is_string($response['description'] ?? null) ? $response['description'] : 'unknown error';
        $parameters = is_array($response['parameters'] ?? null) ? $response['parameters'] : [];
        $retryAfter = is_int($parameters['retry_after'] ?? null) ? $parameters['retry_after'] : null;

        return new self(
            "Telegram Bot API error on {$method}: {$description}",
            $method,
            $code,
            ErrorCodeMap::retryableDefault($code),
            $retryAfter,
        );
    }

    public static function transportFailure(string $method, string $reason): self
    {
        return new self(
            "Telegram Bot API request failed on {$method}: {$reason}",
            $method,
            'internal',
            ErrorCodeMap::retryableDefault('internal'),
        );
    }
}
