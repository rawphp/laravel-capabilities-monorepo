<?php

namespace Rawphp\Capabilities\Adapters\Ai;

/**
 * Bridge to laravel/ai tools (D-011).
 */
interface AiToolAdapter
{
    public function supportsInstalledPeer(): bool;
}
