<?php

namespace Rawphp\Capabilities\Schema;

use RuntimeException;

/**
 * Portable or server validation failure.
 */
final class SchemaValidationException extends RuntimeException
{
    /**
     * @param  list<array{field: string, message: string}>  $violations
     */
    public function __construct(
        string $message,
        public readonly array $violations = [],
        public readonly string $errorCode = 'validation_failed',
    ) {
        parent::__construct($message);
    }

    public static function withViolations(array $violations, string $errorCode = 'validation_failed'): self
    {
        $summary = $violations === []
            ? 'Validation failed'
            : implode('; ', array_map(
                static fn (array $v): string => ($v['field'] ?? '').': '.($v['message'] ?? ''),
                $violations,
            ));

        return new self($summary, $violations, $errorCode);
    }
}
