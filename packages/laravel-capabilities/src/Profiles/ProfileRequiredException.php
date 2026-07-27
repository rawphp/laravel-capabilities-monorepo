<?php

namespace Rawphp\Capabilities\Profiles;

use InvalidArgumentException;

/**
 * Raised when aiTools / mcpTools / meta-tools are called without a profile (D-008 / P2-007).
 */
final class ProfileRequiredException extends InvalidArgumentException
{
    public static function forSurface(string $surface): self
    {
        return new self(sprintf(
            'Profile required for %s tools (D-008). Pass profile, groups, or only — full catalog dump is not allowed.',
            $surface,
        ));
    }
}
