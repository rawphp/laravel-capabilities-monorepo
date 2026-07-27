<?php

namespace Rawphp\Capabilities\Adapters\Ai;

use Rawphp\Capabilities\Adapters\ToolSelection;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;

/**
 * Bridge to laravel/ai tools (D-011). Implementations must not reimplement LLM protocol.
 */
interface AiToolAdapter
{
    public function supportsInstalledPeer(): bool;

    public function adapterApiVersion(): int;

    /**
     * Build peer-shaped tool definitions from a profile selection (D-008).
     *
     * @return list<array<string, mixed>>
     */
    public function toolsFor(ToolSelection|string|array $selection, ?CapabilityContext $ctx = null): array;

    /**
     * Invoke a capability via the registry with caller=agent (D-022).
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $options
     */
    public function handle(string $name, array $input, object $actor, array $options = []): CapabilityResult;

    /**
     * Structured tool response for the agent loop (AI-001).
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function handleStructured(string $name, array $input, object $actor, array $options = []): array;
}
