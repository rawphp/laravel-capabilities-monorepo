<?php

namespace Rawphp\Capabilities\Support;

use RuntimeException;

/**
 * Thrown from run() to refuse with a code other than domain_error.
 * Only codes a domain may own get a named constructor; pipeline codes stay with the pipeline.
 */
final class CapabilityFailure extends RuntimeException
{
    private function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    public static function conflict(string $message): self
    {
        return new self('conflict', $message);
    }

    /**
     * @param  int|null  $retryAfter  seconds the client should wait; sent as Retry-After on HTTP
     */
    public static function rateLimited(string $message, ?int $retryAfter = null): self
    {
        return new self('rate_limited', $message, $retryAfter);
    }
}
