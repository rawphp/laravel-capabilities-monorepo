<?php

namespace Rawphp\Capabilities;

use Rawphp\Capabilities\Registry\CapabilityDefinitionBuilder;

/**
 * Fluent entry point for alternate discovery (D-017).
 *
 * Usage: Capability::define('create-invoice')->description(...)->register($registry)
 * Laravel facade {@see Facades\Capability} can proxy the registry once the container is bound.
 */
final class Capability
{
    public static function define(string $name): CapabilityDefinitionBuilder
    {
        return CapabilityDefinitionBuilder::make($name);
    }
}
