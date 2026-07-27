<?php

namespace Rawphp\Capabilities\Adapters\Mcp;

/**
 * AdapterApi V1 implementation for laravel/mcp (scaffold).
 */
final class McpToolAdapterV1 implements McpToolAdapter
{
    public function supportsInstalledPeer(): bool
    {
        return false;
    }
}
