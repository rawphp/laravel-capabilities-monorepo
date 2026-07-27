<?php

namespace Rawphp\Capabilities\Adapters\Mcp;

/**
 * Bridge to laravel/mcp tools (D-011).
 */
interface McpToolAdapter
{
    public function supportsInstalledPeer(): bool;
}
