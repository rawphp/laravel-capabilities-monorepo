<?php

namespace Rawphp\Capabilities\Support;

use RuntimeException;

/**
 * Thrown when a multi-tenant system job omits first-class tenantId (P2-005 / D-003).
 */
final class MissingJobTenantException extends RuntimeException
{
    public static function forSystemActor(string $name): self
    {
        return new self(sprintf(
            'SystemActor "%s" requires first-class tenantId on the job/context when tenancy is required and capability is not globalSystem (P2-005).',
            $name,
        ));
    }
}
