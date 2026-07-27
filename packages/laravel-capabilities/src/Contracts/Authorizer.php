<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Decides whether an actor may invoke a capability with the given input.
 *
 * Capability classes may implement their own authorize(); this contract is the
 * injectable seam for pipeline-level stubs and shared policy adapters.
 *
 * Unit tests use {@see \Rawphp\Capabilities\Support\StubAuthorizer}.
 */
interface Authorizer
{
    /**
     * @param  mixed  $input    Validated capability input (DTO or array at edges)
     * @param  mixed  $context  CapabilityContext (or null in pure unit stubs)
     */
    public function authorize(string $capability, mixed $input, mixed $context): bool;
}
