<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesMessaging\Tests\Fixtures;

use Psr\Log\AbstractLogger;

/**
 * PSR-3 logger that records entries in memory — test double only.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /**
     * $message stays untyped: psr/log 1.x (illuminate ^11 prefer-lowest) declares it untyped,
     * and a narrower child type is a fatal signature mismatch.
     *
     * @param  string|\Stringable  $message
     * @param  array<string, mixed>  $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }
}
