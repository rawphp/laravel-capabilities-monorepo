<?php

namespace Rawphp\Capabilities\Adapters\Mcp;

use Rawphp\Capabilities\Adapters\ToolSelection;
use Rawphp\Capabilities\Support\CapabilityResult;

/**
 * Bridge to laravel/mcp tools (D-011 / D-023).
 */
interface McpToolAdapter
{
    public function supportsInstalledPeer(): bool;

    public function adapterApiVersion(): int;

    /**
     * Register profile-selected tools with the MCP server surface.
     *
     * @return list<array<string, mixed>>
     */
    public function register(ToolSelection|string|array $selection): array;

    /**
     * tools/call → registry with caller=mcp and resolved auth profile.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $options
     */
    public function handle(
        string $name,
        array $input,
        McpCredential $credential,
        array $options = [],
    ): CapabilityResult;

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function handleStructured(
        string $name,
        array $input,
        McpCredential $credential,
        array $options = [],
    ): array;
}
