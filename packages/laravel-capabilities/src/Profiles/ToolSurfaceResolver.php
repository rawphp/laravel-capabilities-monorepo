<?php

namespace Rawphp\Capabilities\Profiles;

use Rawphp\Capabilities\Pipeline\InvokeAuditStage;
use Rawphp\Capabilities\Pipeline\InvokeObservation;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Registry\DefinitionCatalog;
use Throwable;

/**
 * Profile-filtered tool lists for agent/MCP surfaces (D-008 / P2-007).
 *
 * Extracted from {@see CapabilityRegistry} so
 * discovery filtering stays independent of the invoke pipeline.
 *
 * Boundary events (unfiltered refusal, warn threshold) go to the observation
 * log and, when bound and enabled, the host's durable AuditWriter (D-010).
 */
final class ToolSurfaceResolver
{
    public function __construct(
        private DefinitionCatalog $definitions,
        private ProfileSelector $profileSelector,
        private InvokeObservation $observation,
        /** @var array<string, bool> */
        private array $globallyEnabledSurfaces = [],
        /** @var array<string, mixed> */
        private array $toolSurfaceConfig = [],
        private ?InvokeAuditStage $auditStage = null,
    ) {}

    /**
     * @param  array<string, bool>  $globallyEnabledSurfaces
     */
    public function withGloballyEnabledSurfaces(array $globallyEnabledSurfaces): self
    {
        $this->globallyEnabledSurfaces = $globallyEnabledSurfaces;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $toolSurfaceConfig
     */
    public function withToolSurfaceConfig(array $toolSurfaceConfig): self
    {
        $this->toolSurfaceConfig = $toolSurfaceConfig;

        return $this;
    }

    /**
     * @param  string|array<string, mixed>|list<string>|null  $profile
     * @return list<array<string, mixed>>
     */
    public function toolsForSurface(string $surface, string|array|null $profile, mixed $actor = null): array
    {
        $cfg = $this->toolSurfaceConfig[$surface] ?? [
            'profiles' => [],
            'require_profile' => true,
            'max_tools_warn' => 32,
            'max_tools_hard' => 64,
        ];
        $namedProfiles = $cfg['profiles'] ?? [];
        $requireProfile = (bool) ($cfg['require_profile'] ?? true);
        $resolved = $this->profileSelector->resolve($profile, $namedProfiles);

        if ($resolved['unscoped']) {
            if ($requireProfile) {
                throw ProfileRequiredException::forSurface($surface);
            }
            // Loud deprecation path: empty list, not full catalog dump (D-008).
            $this->recordBoundary(
                'tool_surface.unfiltered_refused',
                sprintf('Unfiltered %s tools requested; returning empty list (D-008).', $surface),
                ['surface' => $surface],
            );

            return [];
        }

        $tools = [];
        foreach ($this->definitions->all() as $definition) {
            $effective = $definition->effectiveSurfaces($this->globallyEnabledSurfaces);
            if (! in_array($surface, $effective, true)) {
                continue;
            }
            if (! $definition->isDiscoverable($actor)) {
                continue;
            }
            if (! $this->profileSelector->matches($definition, $resolved)) {
                continue;
            }
            $tools[] = [
                'name' => $definition->name,
                'description' => $definition->description,
                'input_schema' => $definition->inputSchema(),
            ];
        }

        $count = count($tools);
        $warn = (int) ($cfg['max_tools_warn'] ?? 32);
        $hard = (int) ($cfg['max_tools_hard'] ?? 64);

        if ($count > $hard) {
            throw new TooManyToolsException($count, $hard);
        }
        if ($count > $warn) {
            $this->recordBoundary(
                'tool_surface.warn_threshold_exceeded',
                sprintf('Profile expanded to %d tools (warn threshold %d) for surface %s.', $count, $warn, $surface),
                ['surface' => $surface, 'count' => $count, 'warn' => $warn],
                ['profile' => $profile],
            );
        }

        return $tools;
    }

    /**
     * @param  string|array<string, mixed>|list<string>|null  $profile
     * @return list<array<string, mixed>>
     */
    public function metaToolsForSurface(
        string $surface,
        string|array|null $profile,
        callable $listInProfile,
    ): array {
        $cfg = $this->toolSurfaceConfig[$surface] ?? ['require_profile' => true, 'profiles' => []];
        $requireProfile = (bool) ($cfg['require_profile'] ?? true);
        $resolved = $this->profileSelector->resolve($profile, $cfg['profiles'] ?? []);

        if ($resolved['unscoped']) {
            if ($requireProfile) {
                throw ProfileRequiredException::forSurface($surface);
            }

            return [];
        }

        // Meta-tools inherit the same profile — not a full-catalog escape hatch (P2-007).
        $allowlist = $listInProfile($surface, $profile);

        return [
            [
                'name' => 'capabilities.list',
                'description' => 'List capabilities in profile',
                'profile' => $profile,
                'surface' => $surface,
                'allowlist' => $allowlist,
            ],
            [
                'name' => 'capabilities.invoke',
                'description' => 'Invoke a capability by name within profile',
                'profile' => $profile,
                'surface' => $surface,
                'allowlist' => $allowlist,
            ],
        ];
    }

    /**
     * Log a D-008 boundary event; also write it to the audit trail when a writer is
     * bound and audit is enabled. Audit failure never blocks tool listing.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $auditExtra
     */
    private function recordBoundary(string $event, string $message, array $context, array $auditExtra = []): void
    {
        $this->observation->logs[] = ['level' => 'warning', 'message' => $message, 'context' => $context];

        $writer = $this->auditStage?->auditEnabled ? $this->auditStage->auditWriter : null;
        if ($writer === null) {
            return;
        }

        try {
            $writer->write(['event' => $event, 'payload' => $context + $auditExtra]);
        } catch (Throwable $e) {
            $this->observation->logs[] = [
                'level' => 'warning',
                'message' => sprintf('Audit failed for %s: %s', $event, $e->getMessage()),
                'context' => $context,
            ];
        }
    }
}
