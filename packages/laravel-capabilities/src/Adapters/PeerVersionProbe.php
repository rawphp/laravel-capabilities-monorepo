<?php

namespace Rawphp\Capabilities\Adapters;

/**
 * Feature-detect installed laravel/ai and laravel/mcp (D-011).
 *
 * Uses class/interface presence and optional version strings — never boots live peers.
 * Unit tests inject class-exists + version maps; production uses class_exists + Composer.
 */
final class PeerVersionProbe
{
    public const PEER_AI = 'laravel/ai';

    public const PEER_MCP = 'laravel/mcp';

    /**
     * Representative classes for feature detection (not version-locked).
     *
     * @var array<string, list<string>>
     */
    private const PEER_CLASSES = [
        self::PEER_AI => [
            'Laravel\\Ai\\AiServiceProvider',
            'Laravel\\Ai\\Contracts\\Tool',
            'Laravel\\Ai\\Tool',
        ],
        self::PEER_MCP => [
            'Laravel\\Mcp\\Server',
            'Laravel\\Mcp\\Server\\McpServiceProvider',
            'Laravel\\Mcp\\Facades\\Mcp',
        ],
    ];

    /**
     * @param  callable(string): bool|null  $classExists
     * @param  array<string, bool>  $installedOverrides  peer => installed (tests)
     * @param  array<string, bool>  $compatibleOverrides  peer => compatible (tests)
     * @param  array<string, string|null>  $versions  peer => installed version string
     * @param  array<string, list<string>>  $supportedVersions  peer => allowed version strings / '*'
     */
    public function __construct(
        private readonly mixed $classExists = null,
        private readonly array $installedOverrides = [],
        private readonly array $compatibleOverrides = [],
        private readonly array $versions = [],
        private readonly array $supportedVersions = [
            self::PEER_AI => ['*'],
            self::PEER_MCP => ['*'],
        ],
    ) {}

    public static function forMissingPeers(): self
    {
        return new self(
            classExists: static fn (string $class): bool => false,
            installedOverrides: [
                self::PEER_AI => false,
                self::PEER_MCP => false,
            ],
            compatibleOverrides: [
                self::PEER_AI => false,
                self::PEER_MCP => false,
            ],
        );
    }

    /**
     * @param  array<string, bool>  $peers  peer => compatible (implies installed)
     */
    public static function fake(array $peers): self
    {
        $installed = [];
        $compatible = [];
        foreach ($peers as $peer => $ok) {
            $installed[$peer] = (bool) $ok;
            $compatible[$peer] = (bool) $ok;
        }

        return new self(
            classExists: static fn (string $class): bool => false,
            installedOverrides: $installed,
            compatibleOverrides: $compatible,
            versions: array_map(
                static fn (bool $ok): ?string => $ok ? '1.0.0-test' : null,
                $peers,
            ),
        );
    }

    public function isInstalled(string $peer): bool
    {
        if (array_key_exists($peer, $this->installedOverrides)) {
            return $this->installedOverrides[$peer];
        }

        $checker = $this->classExists ?? 'class_exists';
        foreach (self::PEER_CLASSES[$peer] ?? [] as $class) {
            if ((bool) $checker($class)) {
                return true;
            }
        }

        return false;
    }

    public function isCompatible(string $peer): bool
    {
        if (array_key_exists($peer, $this->compatibleOverrides)) {
            return $this->compatibleOverrides[$peer];
        }

        if (! $this->isInstalled($peer)) {
            return false;
        }

        $version = $this->installedVersion($peer);
        $allowed = $this->supportedVersions[$peer] ?? ['*'];

        if (in_array('*', $allowed, true)) {
            return true;
        }

        if ($version === null) {
            // Installed via feature-detect without version pin: treat as compatible.
            return true;
        }

        return in_array($version, $allowed, true);
    }

    /**
     * True when peer is installed and compatible — used by adapters' supportsInstalledPeer().
     */
    public function supports(string $peer): bool
    {
        return $this->isInstalled($peer) && $this->isCompatible($peer);
    }

    public function installedVersion(string $peer): ?string
    {
        return $this->versions[$peer] ?? null;
    }

    /**
     * Feature-detect a peer; returns structured probe result for contract tests.
     *
     * @return array{peer: string, installed: bool, compatible: bool, version: string|null, adapter_api: int}
     */
    public function probe(string $peer): array
    {
        return [
            'peer' => $peer,
            'installed' => $this->isInstalled($peer),
            'compatible' => $this->isCompatible($peer),
            'version' => $this->installedVersion($peer),
            'adapter_api' => AdapterApi::CURRENT,
        ];
    }
}
