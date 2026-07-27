<?php

namespace Rawphp\Capabilities\Schema;

use InvalidArgumentException;
use Rawphp\Capabilities\Registry\CapabilityRegistry;

/**
 * Adapters must export tool schemas from the registry — never a hand-copied second source (D-004).
 */
final class ToolSchemaExporter
{
    public const ADAPTERS = ['ai', 'mcp', 'http_catalog', 'cli_catalog'];

    public function __construct(
        private readonly CapabilityRegistry $registry,
    ) {}

    /**
     * @return array{name: string, description: string, input_schema: array<string, mixed>|null, output_schema: array<string, mixed>|null}
     */
    public function export(string $adapter, string $capabilityName): array
    {
        if (! in_array($adapter, self::ADAPTERS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown adapter "%s".', $adapter));
        }

        $definition = $this->registry->get($capabilityName);

        // Single source: definition JSON Schema from DTO reflection — no adapter-local schema map.
        return [
            'adapter' => $adapter,
            'name' => $definition->name,
            'description' => $definition->description,
            'input_schema' => $definition->inputSchema(),
            'output_schema' => $definition->outputSchema(),
            'source' => 'registry',
        ];
    }

    /**
     * Assert adapter schema is identical to registry (identity check for unit tests).
     *
     * @param  array<string, mixed>|null  $handCopied  forbidden second source
     */
    public function assertUsesRegistry(string $adapter, string $capabilityName, ?array $handCopied = null): bool
    {
        $exported = $this->export($adapter, $capabilityName);
        $registrySchema = $this->registry->get($capabilityName)->inputSchema();

        if ($handCopied !== null && $handCopied !== $registrySchema) {
            return false;
        }

        return $exported['input_schema'] === $registrySchema && $exported['source'] === 'registry';
    }
}
