<?php

namespace Rawphp\Capabilities\Boot;

use Rawphp\Capabilities\Adapters\PeerIncompatibleException;
use Rawphp\Capabilities\Adapters\PeerSurfaceBootstrap;
use Rawphp\Capabilities\Adapters\PeerSurfaceStatus;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Registry\CapabilityDefinition;

/**
 * Service-provider boot rules (SURF-004 / D-007 / D-011 / D-021).
 *
 * Pure / unit-testable: inject probe + package presence flags; no Laravel app boot.
 */
final class BootGuard
{
    /**
     * @param  array<string, mixed>  $config  full capabilities config
     */
    public function __construct(
        private readonly array $config = [],
        private readonly ?PeerVersionProbe $probe = null,
        private readonly bool $messagingPackageInstalled = false,
        private readonly string $appEnv = 'testing',
        private readonly bool $skipBootChecks = false,
    ) {}

    public static function fromDefaults(?PeerVersionProbe $probe = null): self
    {
        return new self(
            config: CapabilitiesConfig::defaults(),
            probe: $probe ?? PeerVersionProbe::forMissingPeers(),
        );
    }

    /**
     * Run all boot-time surface dependency + peer checks.
     *
     * @return array{
     *     surfaces: array<string, PeerSurfaceStatus|array{status: string, registers: bool}>,
     *     logs: list<array{level: string, message: string, context?: array<string, mixed>}>,
     *     skipped_deferred: bool
     * }
     */
    public function validate(): array
    {
        $surfaces = $this->config['surfaces'] ?? CapabilitiesConfig::defaults()['surfaces'];
        $probe = $this->probe ?? PeerVersionProbe::forMissingPeers();
        $logs = [];
        $statuses = [];

        $this->assertCliRequiresHttp($surfaces);
        $this->assertMessagingRules($surfaces);
        $this->assertSkipBootChecksPolicy();

        $bootstrap = new PeerSurfaceBootstrap($probe);

        foreach ([SurfaceNames::AGENT, SurfaceNames::MCP] as $surface) {
            $peer = SurfaceNames::PEER_PACKAGES[$surface];
            $cfg = (array) ($surfaces[$surface] ?? []);
            try {
                $status = $bootstrap->evaluate($surface, $peer, $cfg);
                $statuses[$surface] = $status;
                foreach ($status->logs as $log) {
                    $logs[] = $log;
                }
            } catch (PeerIncompatibleException $e) {
                throw $e;
            }
        }

        foreach (SurfaceNames::ALL as $surface) {
            if (isset($statuses[$surface])) {
                continue;
            }
            $enabled = (bool) (($surfaces[$surface]['enabled'] ?? null) ?? ($surface !== SurfaceNames::MESSAGING));
            $statuses[$surface] = [
                'status' => $enabled ? PeerSurfaceStatus::UP : PeerSurfaceStatus::DISABLED_CONFIG,
                'registers' => $enabled && SurfaceRegistrar::isRegistered($surface, $surfaces, $probe),
            ];
        }

        $skipDeferred = $this->shouldSkipDeferredChecks();

        return [
            'surfaces' => $statuses,
            'logs' => $logs,
            'skipped_deferred' => $skipDeferred,
        ];
    }

    /**
     * Evaluate a single peer surface (agent/mcp) without full validate.
     */
    public function evaluatePeer(string $surface): PeerSurfaceStatus
    {
        $surfaces = $this->config['surfaces'] ?? [];
        $cfg = (array) ($surfaces[$surface] ?? ['enabled' => true, 'require_package' => true, 'on_incompatible' => 'fail']);
        $peer = SurfaceNames::PEER_PACKAGES[$surface] ?? $surface;
        $probe = $this->probe ?? PeerVersionProbe::forMissingPeers();

        return (new PeerSurfaceBootstrap($probe))->evaluate($surface, $peer, $cfg);
    }

    /**
     * CAPABILITIES_SKIP_BOOT_CHECKS: only deferred-style checks in non-production (D-021).
     */
    public function shouldSkipDeferredChecks(): bool
    {
        if (! $this->skipBootChecks) {
            return false;
        }

        if ($this->isProduction()) {
            return false;
        }

        return true;
    }

    public function isProduction(): bool
    {
        return strtolower($this->appEnv) === 'production';
    }

    /**
     * Telegram/Slack secrets are never required at boot — only on first traffic (D-021 / BOOT-RULE).
     */
    public function requiresMessagingSecretsAtBoot(): bool
    {
        return false;
    }

    /**
     * CI must run adapter contract tests before release (BOOT-RULE documentation gate).
     */
    public static function adapterContractTestsRequiredBeforeRelease(): bool
    {
        return true;
    }

    /**
     * Effective exposure: capability.surfaces ∩ globally enabled (SURF-001).
     *
     * @param  list<string>  $capabilitySurfaces
     * @param  array<string, bool>  $globallyEnabled
     * @return list<string>
     */
    public static function effectiveSurfaces(array $capabilitySurfaces, array $globallyEnabled): array
    {
        $definition = new CapabilityDefinition(
            name: 'probe',
            description: 'probe',
            surfaces: array_values($capabilitySurfaces),
            readOnly: true,
        );

        return $definition->effectiveSurfaces($globallyEnabled);
    }

    /**
     * @param  list<string>  $capabilitySurfaces
     * @param  array<string, bool>  $globallyEnabled
     */
    public static function isEffectivelyExposed(
        string $surface,
        array $capabilitySurfaces,
        array $globallyEnabled,
    ): bool {
        return in_array($surface, self::effectiveSurfaces($capabilitySurfaces, $globallyEnabled), true);
    }

    /**
     * @param  array<string, mixed>  $surfaces
     */
    private function assertCliRequiresHttp(array $surfaces): void
    {
        $cliOn = (bool) ($surfaces[SurfaceNames::CLI]['enabled'] ?? true);
        $httpOn = (bool) ($surfaces[SurfaceNames::HTTP]['enabled'] ?? true);
        if ($cliOn && ! $httpOn) {
            throw BootException::cliRequiresHttp();
        }
    }

    /**
     * @param  array<string, mixed>  $surfaces
     */
    private function assertMessagingRules(array $surfaces): void
    {
        $msgOn = (bool) ($surfaces[SurfaceNames::MESSAGING]['enabled'] ?? false);
        if (! $msgOn) {
            return;
        }

        $agentOn = (bool) ($surfaces[SurfaceNames::AGENT]['enabled'] ?? true);
        if (! $agentOn) {
            throw BootException::messagingRequiresAgent();
        }

        if (! $this->messagingPackageInstalled) {
            throw BootException::messagingRequiresPackage();
        }
    }

    private function assertSkipBootChecksPolicy(): void
    {
        if ($this->skipBootChecks && $this->isProduction()) {
            // Forbidden in production: ignore skip (do not actually skip) — D-021.
            // Loud path: some deploys prefer exception; we fail closed by not skipping.
            // Documented rule remains enforceable via shouldSkipDeferredChecks() === false.
        }
    }
}
