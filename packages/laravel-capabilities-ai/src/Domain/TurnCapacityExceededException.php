<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use RuntimeException;

/**
 * Queued + running turns are at max_concurrent_turns; nothing was persisted or dispatched.
 * Retryable: the caller may resend once active turns finish (HTTP maps to 429).
 */
final class TurnCapacityExceededException extends RuntimeException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct("Too many active turns ({$limit} queued or running); retry later");
    }
}
