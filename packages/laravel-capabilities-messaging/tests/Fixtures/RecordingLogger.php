<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesMessaging\Tests\Fixtures;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * PSR-3 logger that records entries in memory — test double only.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }
}
