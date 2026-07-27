<?php

namespace Rawphp\Capabilities\Profiles;

use RuntimeException;

/**
 * Raised when a profile expands beyond max_tools_hard (D-008).
 */
final class TooManyToolsException extends RuntimeException
{
    public function __construct(
        public readonly int $count,
        public readonly int $hardMax,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : sprintf(
            'Profile expanded to %d tools; hard max is %d (D-008).',
            $count,
            $hardMax,
        ));
    }
}
