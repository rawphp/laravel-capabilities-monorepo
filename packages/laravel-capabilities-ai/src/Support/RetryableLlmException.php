<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use RuntimeException;
use Throwable;

/**
 * Transient LLM failure (rate limit, overload, 5xx, connection) — the provider may
 * succeed if the caller re-drives later. Anything else an {@see LlmClient}
 * throws is treated as permanent.
 */
final class RetryableLlmException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
