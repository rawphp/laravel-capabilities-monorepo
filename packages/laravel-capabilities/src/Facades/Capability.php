<?php

namespace Rawphp\Capabilities\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Rawphp\Capabilities\Registry\CapabilityRegistry
 */
final class Capability extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'capabilities.registry';
    }
}
