<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Adapters\AdapterApi;
use Rawphp\Capabilities\Adapters\PeerSupportMatrix;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;

/**
 * Frozen peer-facing contract snapshots for AdapterApi V1 (D-011 / REQ-037).
 *
 * These fixtures are intentionally duplicated from adapter/probe implementations
 * so unit tests fail when AI/MCP mapping, PEER_CLASSES, or AdapterApi shapes drift.
 * Update fixtures + bump AdapterApi when the bridge contract changes on purpose.
 *
 * Never requires live laravel/ai or laravel/mcp packages.
 */
final class PeerContractFixtures
{
    /**
     * AdapterApi version these fixtures freeze.
     */
    public static function adapterApiVersion(): int
    {
        return 1;
    }

    /**
     * @return list<int>
     */
    public static function supportedAdapterApis(): array
    {
        return [self::adapterApiVersion()];
    }

    /**
     * Expected keys on each AI peer tool array (AiToolAdapterV1::mapToPeerTool).
     *
     * @return list<string>
     */
    public static function aiToolKeys(): array
    {
        return [
            'name',
            'description',
            'input_schema',
            'source',
            'adapter_api',
            'peer',
        ];
    }

    /**
     * Expected keys on each MCP registration tool array (McpToolAdapterV1::mapToPeerTool).
     *
     * @return list<string>
     */
    public static function mcpToolKeys(): array
    {
        return self::aiToolKeys();
    }

    public static function aiPeer(): string
    {
        return PeerVersionProbe::PEER_AI;
    }

    public static function mcpPeer(): string
    {
        return PeerVersionProbe::PEER_MCP;
    }

    public static function toolSource(): string
    {
        return 'registry';
    }

    /**
     * Documented PEER_CLASSES for feature detection (must match PeerVersionProbe).
     *
     * @return array<string, list<string>>
     */
    public static function peerClasses(): array
    {
        return [
            PeerVersionProbe::PEER_AI => [
                'Laravel\\Ai\\AiServiceProvider',
                'Laravel\\Ai\\Contracts\\Tool',
                'Laravel\\Ai\\Tool',
            ],
            PeerVersionProbe::PEER_MCP => [
                'Laravel\\Mcp\\Server',
                'Laravel\\Mcp\\Server\\McpServiceProvider',
                'Laravel\\Mcp\\Facades\\Mcp',
            ],
        ];
    }

    /**
     * Documented matrix cells (must match PeerSupportMatrix::constraints()).
     *
     * @return array<string, list<string>>
     */
    public static function matrixCells(): array
    {
        return [
            PeerSupportMatrix::PEER_AI => ['^0.1', '^1.0'],
            PeerSupportMatrix::PEER_MCP => ['^0.1', '^1.0'],
        ];
    }

    /**
     * Full AdapterApi V1 bridge shape for requiresBump comparisons.
     *
     * @return array<string, mixed>
     */
    public static function adapterApiShape(): array
    {
        return [
            'version' => self::adapterApiVersion(),
            'current' => self::adapterApiVersion(),
            'supported' => self::supportedAdapterApis(),
            'ai_tool_keys' => self::aiToolKeys(),
            'mcp_tool_keys' => self::mcpToolKeys(),
            'ai_peer' => self::aiPeer(),
            'mcp_peer' => self::mcpPeer(),
            'tool_source' => self::toolSource(),
            'peer_classes' => self::peerClasses(),
            'matrix_cells' => self::matrixCells(),
            'structured_success_keys' => self::structuredSuccessKeys(),
            'structured_error_keys' => self::structuredErrorKeys(),
            'structured_error_body_keys' => self::structuredErrorBodyKeys(),
            'probe_result_keys' => self::probeResultKeys(),
        ];
    }

    /**
     * Structural template of an AI tool entry (keys + fixed field values).
     *
     * @return array<string, mixed>
     */
    public static function aiToolShapeTemplate(): array
    {
        return [
            'keys' => self::sortedKeys(self::aiToolKeys()),
            'source' => self::toolSource(),
            'adapter_api' => self::adapterApiVersion(),
            'peer' => self::aiPeer(),
        ];
    }

    /**
     * Structural template of an MCP tool entry.
     *
     * @return array<string, mixed>
     */
    public static function mcpToolShapeTemplate(): array
    {
        return [
            'keys' => self::sortedKeys(self::mcpToolKeys()),
            'source' => self::toolSource(),
            'adapter_api' => self::adapterApiVersion(),
            'peer' => self::mcpPeer(),
        ];
    }

    /**
     * Reduce a live tool array to the comparable shape used with requiresBump.
     *
     * @param  array<string, mixed>  $tool
     * @return array<string, mixed>
     */
    public static function shapeFromTool(array $tool): array
    {
        return [
            'keys' => self::sortedKeys(array_keys($tool)),
            'source' => $tool['source'] ?? null,
            'adapter_api' => $tool['adapter_api'] ?? null,
            'peer' => $tool['peer'] ?? null,
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private static function sortedKeys(array $keys): array
    {
        $keys = array_values($keys);
        sort($keys);

        return $keys;
    }

    /**
     * @return list<string>
     */
    public static function structuredSuccessKeys(): array
    {
        return ['ok', 'data', 'meta'];
    }

    /**
     * @return list<string>
     */
    public static function structuredErrorKeys(): array
    {
        return ['ok', 'error', 'meta'];
    }

    /**
     * @return list<string>
     */
    public static function structuredErrorBodyKeys(): array
    {
        return ['code', 'message', 'structured', 'retryable', 'details'];
    }

    /**
     * @return list<string>
     */
    public static function probeResultKeys(): array
    {
        return ['peer', 'installed', 'compatible', 'version', 'adapter_api'];
    }

    /**
     * Sanity: fixtures claim the same CURRENT as AdapterApi (cross-check helper).
     */
    public static function assertsCurrentAdapterApi(): bool
    {
        return AdapterApi::CURRENT === self::adapterApiVersion()
            && AdapterApi::supported() === self::supportedAdapterApis();
    }
}
