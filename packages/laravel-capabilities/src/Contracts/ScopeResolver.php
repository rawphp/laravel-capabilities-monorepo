<?php

namespace Rawphp\Capabilities\Contracts;

use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityScope;

/**
 * App-supplied tenant / team scope resolution (D-003).
 *
 * Called after actor is known, before authorize/run.
 * SystemActor tenant comes from first-class job/context fields only (P2-005).
 */
interface ScopeResolver
{
    /**
     * Resolve the active tenancy boundary for this invoke.
     */
    public function resolve(CapabilityContext $partial): CapabilityScope;
}
