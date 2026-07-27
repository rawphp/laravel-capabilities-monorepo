<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Canonical capability class contract (D-017).
 *
 * Concrete authorize / run signatures use typed input DTOs on implementations.
 * The registry discovers classes via #[Capability] + this marker (or fluent define).
 */
interface DefinesCapability
{
    // Implementations define typed methods, for example:
    // public function authorize(Input $input, CapabilityContext $ctx): bool;
    // public function needsApproval(Input $input, CapabilityContext $ctx): bool;
    // public function run(Input $input, CapabilityContext $ctx): Output|CapabilityResult;
}
