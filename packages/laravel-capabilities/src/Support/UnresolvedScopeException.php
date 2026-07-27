<?php

namespace Rawphp\Capabilities\Support;

use RuntimeException;

/**
 * Scope could not be resolved when tenancy is required (D-003).
 */
final class UnresolvedScopeException extends RuntimeException
{
    public static function systemWithoutTenant(): self
    {
        return new self(
            'System jobs must declare tenantId on the job/context, not in input (P2-005).',
        );
    }

    public static function unusable(): self
    {
        return new self('Unable to resolve a usable CapabilityScope when tenancy is required (D-003).');
    }
}
