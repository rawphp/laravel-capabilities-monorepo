<?php

namespace Rawphp\Capabilities\Schema;

use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;

/**
 * Catalog list/describe with JSON Schema only (D-004, CAT-001).
 */
final class CatalogPresenter
{
    public function __construct(
        private readonly CapabilityRegistry $registry,
    ) {}

    /**
     * Compact list — can omit full schemas until describe (CAT-001).
     *
     * @return list<array<string, mixed>>
     */
    public function list(bool $includeSchemas = false): array
    {
        $entries = [];
        foreach ($this->registry->definitions() as $definition) {
            if (! $definition->hasEffectiveExposure($this->registry->globallyEnabledSurfaces())) {
                continue;
            }
            $entries[] = $includeSchemas
                ? $this->describe($definition->name)
                : $this->compactEntry($definition);
        }

        return $entries;
    }

    /**
     * Full describe with input_schema / output_schema (JSON Schema only).
     *
     * @return array<string, mixed>
     */
    public function describe(string $nameOrAlias): array
    {
        $definition = $this->registry->get($nameOrAlias);
        $inputSchema = $definition->inputSchema();
        $outputSchema = $definition->outputSchema();

        return [
            'name' => $definition->name,
            'description' => $definition->description,
            'surfaces' => $definition->effectiveSurfaces($this->registry->globallyEnabledSurfaces()),
            'readOnly' => $definition->readOnly,
            'input_schema' => $inputSchema,
            'output_schema' => $outputSchema,
            'schema_version' => $definition->schemaVersion,
            'deprecated' => $definition->deprecated,
            'aliases' => $definition->aliases,
            'groups' => $definition->groups,
            'tags' => $definition->tags,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function compactEntry(CapabilityDefinition $definition): array
    {
        return [
            'name' => $definition->name,
            'description' => $definition->description,
            'surfaces' => $definition->effectiveSurfaces($this->registry->globallyEnabledSurfaces()),
            'schema_version' => $definition->schemaVersion,
            'readOnly' => $definition->readOnly,
        ];
    }
}
