<?php

namespace Rawphp\Capabilities\Adapters\Mcp;

use InvalidArgumentException;
use Rawphp\Capabilities\Registry\CapabilityRegistry;

/**
 * Validates MCP profile allowlists: capability names must exist and enable the mcp surface (D-024 / ORI-842).
 *
 * Profiles are {@code name => list<string>} capability names only (D-008) — not a second resolver;
 * {@see CapabilityRegistry::mcpTools} still expands tools after names are known-good.
 */
final class McpProfileValidator
{
    /**
     * @param  list<string>  $capabilityNames
     */
    public static function assertAllowlist(
        CapabilityRegistry $registry,
        string $profileName,
        array $capabilityNames,
    ): void {
        foreach ($capabilityNames as $name) {
            $name = (string) $name;
            if ($name === '') {
                throw new InvalidArgumentException(
                    "MCP profile [{$profileName}] contains an empty capability name"
                );
            }
            if (! $registry->has($name)) {
                throw new InvalidArgumentException(
                    "MCP profile [{$profileName}] lists unknown capability [{$name}]"
                );
            }
            $def = $registry->get($name);
            if (! in_array('mcp', $def->surfaces, true)) {
                throw new InvalidArgumentException(
                    "MCP profile [{$profileName}] lists [{$name}] which does not enable the mcp surface"
                );
            }
        }
    }
}
