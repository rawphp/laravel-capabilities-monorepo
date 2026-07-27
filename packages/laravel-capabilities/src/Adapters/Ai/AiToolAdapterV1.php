<?php

namespace Rawphp\Capabilities\Adapters\Ai;

/**
 * AdapterApi V1 implementation for laravel/ai (scaffold).
 */
final class AiToolAdapterV1 implements AiToolAdapter
{
    public function supportsInstalledPeer(): bool
    {
        return false;
    }
}
