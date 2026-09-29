<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use RuntimeException;

/**
 * The user hit turns_per_minute (D-013); nothing was persisted or dispatched.
 * Retryable: the caller may resend after the window (HTTP maps to 429 rate_limited).
 */
final class TurnRateLimitedException extends RuntimeException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct("Too many messages ({$limit} per minute); retry later");
    }
}
