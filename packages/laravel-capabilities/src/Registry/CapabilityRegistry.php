<?php

namespace Rawphp\Capabilities\Registry;

/**
 * Central choke point: validate → authz → approve → run → audit.
 * Surfaces are adapters; only this path mutates product state.
 */
final class CapabilityRegistry
{
}
