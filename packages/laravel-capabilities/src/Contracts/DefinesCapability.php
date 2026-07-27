<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Canonical capability class contract (scaffold).
 *
 * Concrete authorize / run signatures use typed input DTOs on implementations.
 */
interface DefinesCapability
{
    // Implementations define:
    // public function authorize(Input $input, CapabilityContext $ctx): bool;
    // public function needsApproval(Input $input, CapabilityContext $ctx): bool;
    // public function run(Input $input, CapabilityContext $ctx): Output;
}
