<?php

namespace Rawphp\Capabilities\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * A deliberate refusal from run() with a specific mapped error code, such as
 * conflict (409) or rate_limited (429). Any other throw stays domain_error.
 */
final class CapabilityFailure extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $extra  merged into the error envelope (e.g. retry_after)
     */
    public function __construct(
        private readonly string $failureCode,
        string $message,
        private readonly array $extra = [],
    ) {
        if (! ErrorCodeMap::isKnown($failureCode)) {
            throw new InvalidArgumentException(sprintf('Unknown capability error code "%s".', $failureCode));
        }

        parent::__construct($message);
    }

    public static function conflict(string $message): self
    {
        return new self('conflict', $message);
    }

    public static function rateLimited(string $message, int $retryAfter): self
    {
        return new self('rate_limited', $message, ['retry_after' => $retryAfter]);
    }

    public function code(): string
    {
        return $this->failureCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function extra(): array
    {
        return $this->extra;
    }
}
