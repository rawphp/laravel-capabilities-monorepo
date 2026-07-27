<?php

namespace Rawphp\Capabilities\Schema;

use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;

/**
 * Catalog list/describe with JSON Schema only (D-004, CAT-001, D-012).
 */
final class CatalogPresenter
{
    private CatalogHealth $health;

    public function __construct(
        private readonly CapabilityRegistry $registry,
        ?CatalogHealth $health = null,
    ) {
        $this->health = $health ?? new CatalogHealth;
    }

    /**
     * Compact list — can omit full schemas until describe (CAT-001).
     *
     * @param  array{
     *     include_schemas?: bool,
     *     caller?: string|null,
     *     surface?: string|null
     * }  $options
     * @return list<array<string, mixed>>
     */
    public function list(bool $includeSchemas = false, array $options = []): array
    {
        $includeSchemas = $includeSchemas || (bool) ($options['include_schemas'] ?? false);
        $callerSurface = $options['caller'] ?? $options['surface'] ?? null;

        $entries = [];
        foreach ($this->registry->definitions() as $definition) {
            if (! $definition->hasEffectiveExposure($this->registry->globallyEnabledSurfaces())) {
                continue;
            }
            if (is_string($callerSurface) && $callerSurface !== '') {
                $effective = $definition->effectiveSurfaces($this->registry->globallyEnabledSurfaces());
                if (! in_array($callerSurface, $effective, true)) {
                    continue;
                }
            }
            $entries[] = $includeSchemas
                ? $this->describe($definition->name)
                : $this->compactEntry($definition);
        }

        return $entries;
    }

    /**
     * Wire list envelope with optional etag / schema_version for CLI cache (D-004).
     *
     * @param  array<string, mixed>  $options
     * @return array{capabilities: list<array<string, mixed>>, schema_version: string, etag: string}
     */
    public function listEnvelope(bool $includeSchemas = false, array $options = []): array
    {
        $capabilities = $this->list($includeSchemas, $options);

        return [
            'capabilities' => $capabilities,
            'schema_version' => $this->aggregateSchemaVersion($capabilities),
            'etag' => $this->etag($capabilities),
        ];
    }

    /**
     * Full describe with input_schema / output_schema (JSON Schema only).
     *
     * @return array<string, mixed>
     */
    public function describe(string $nameOrAlias): array
    {
        $definition = $this->registry->get($nameOrAlias);

        return $this->fullEntry($definition);
    }

    /**
     * Surface health (D-011 / D-021).
     *
     * @param  array<string, string>  $peerStatus
     * @return array<string, mixed>
     */
    public function health(array $peerStatus = []): array
    {
        $overrides = $this->registry->surfaceHealthOverrides();
        $peerStatus = array_merge($peerStatus, $overrides);

        return $this->health->report(
            $this->registry->globallyEnabledSurfaces(),
            $peerStatus,
        );
    }

    /**
     * Stable etag for cache invalidation.
     *
     * @param  list<array<string, mixed>>|null  $capabilities
     */
    public function etag(?array $capabilities = null): string
    {
        $capabilities ??= $this->list();
        $material = [];
        foreach ($capabilities as $entry) {
            $material[] = ($entry['name'] ?? '').'@'.($entry['schema_version'] ?? '1');
        }
        sort($material);

        return 'W/"'.hash('xxh3', implode('|', $material)).'"';
    }

    /**
     * @return array<string, mixed>
     */
    private function compactEntry(CapabilityDefinition $definition): array
    {
        return $this->metadataFields($definition);
    }

    /**
     * @return array<string, mixed>
     */
    private function fullEntry(CapabilityDefinition $definition): array
    {
        $entry = $this->metadataFields($definition);
        $entry['input_schema'] = $definition->inputSchema();
        $entry['output_schema'] = $definition->outputSchema();

        return $entry;
    }

    /**
     * Shared list/describe metadata (CAT-001 / D-012). No Laravel rule strings.
     *
     * @return array<string, mixed>
     */
    private function metadataFields(CapabilityDefinition $definition): array
    {
        return [
            'name' => $definition->name,
            'description' => $definition->description,
            'surfaces' => $definition->effectiveSurfaces($this->registry->globallyEnabledSurfaces()),
            'readOnly' => $definition->readOnly,
            'schema_version' => $definition->schemaVersion,
            'idempotent' => $definition->idempotent,
            'deprecated' => $definition->deprecated,
            'deprecated_at' => $definition->deprecated_at,
            'aliases' => $definition->aliases,
            'successor' => $definition->successor,
            'sunset_at' => $definition->sunset_at,
            'groups' => $definition->groups,
            'tags' => $definition->tags,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $capabilities
     */
    private function aggregateSchemaVersion(array $capabilities): string
    {
        if ($capabilities === []) {
            return '0';
        }

        $versions = array_map(
            static fn (array $c): string => (string) ($c['schema_version'] ?? '1'),
            $capabilities,
        );

        return hash('xxh3', implode(',', $versions));
    }
}
