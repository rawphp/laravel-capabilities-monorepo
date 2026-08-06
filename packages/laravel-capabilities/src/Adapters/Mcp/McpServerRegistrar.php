<?php

namespace Rawphp\Capabilities\Adapters\Mcp;

use Rawphp\Capabilities\Adapters\PeerSurfaceBootstrap;
use Rawphp\Capabilities\Adapters\PeerSurfaceStatus;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;

/**
 * Config-driven auto-registration of laravel/mcp servers (ORI-790 / D-008 / D-011).
 *
 * Each named profile under surfaces.mcp.profiles becomes one MCP server exposing
 * tools from {@see McpToolAdapter} — host enables the surface + installs the peer;
 * no hand-wiring every capability tool.
 *
 * Pure / unit-testable: peer facade wiring is an optional sink; never requires live laravel/mcp.
 */
final class McpServerRegistrar
{
    public const DEFAULT_PATH_PREFIX = '/mcp';

    /**
     * Server plan without resolving tools (boot tables / diagnostics).
     *
     * @param  array{
     *     enabled?: bool,
     *     require_package?: bool,
     *     on_incompatible?: string,
     *     require_profile?: bool,
     *     auto_register?: bool,
     *     path_prefix?: string,
     *     profiles?: array<string, list<string>|array<string, mixed>>,
     *     servers?: array<string, array{profile?: string, path?: string, middleware?: list<string>}|string>
     * }  $mcpConfig
     * @return list<array{
     *     name: string,
     *     profile: string,
     *     path: string,
     *     source: string,
     *     middleware: list<string>
     * }>
     */
    public static function plan(array $mcpConfig, ?PeerVersionProbe $probe = null): array
    {
        if (! self::shouldRegister($mcpConfig)) {
            return [];
        }

        $status = self::peerStatus($mcpConfig, $probe);
        if (! $status->registersTools) {
            return [];
        }

        return self::serverRows($mcpConfig);
    }

    /**
     * Register profile tools via the adapter and return full server definitions.
     *
     * @param  array<string, mixed>  $mcpConfig
     * @return list<array{
     *     name: string,
     *     profile: string,
     *     path: string,
     *     source: string,
     *     middleware: list<string>,
     *     tools: list<array<string, mixed>>,
     *     adapter_api: int
     * }>
     */
    public static function register(
        array $mcpConfig,
        McpToolAdapter $adapter,
        ?PeerVersionProbe $probe = null,
    ): array {
        $rows = self::plan($mcpConfig, $probe);
        if ($rows === []) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $tools = $adapter->register($row['profile']);
            $out[] = array_merge($row, [
                'tools' => $tools,
                'adapter_api' => $adapter->adapterApiVersion(),
            ]);
        }

        return $out;
    }

    /**
     * Register and push each server definition into a sink (peer facade / host glue).
     *
     * @param  array<string, mixed>  $mcpConfig
     * @param  callable(array<string, mixed>): void  $sink
     * @return list<string> registered server names
     */
    public static function registerInto(
        array $mcpConfig,
        McpToolAdapter $adapter,
        callable $sink,
        ?PeerVersionProbe $probe = null,
    ): array {
        $servers = self::register($mcpConfig, $adapter, $probe);
        $names = [];
        foreach ($servers as $server) {
            $sink($server);
            $names[] = (string) $server['name'];
        }

        return $names;
    }

    /**
     * Artifact keys for SurfaceRegistrar / registration plan tables.
     *
     * @param  array<string, mixed>  $mcpConfig
     * @return list<string>
     */
    public static function artifactKeys(array $mcpConfig, ?PeerVersionProbe $probe = null): array
    {
        try {
            $rows = self::plan($mcpConfig, $probe);
        } catch (\Throwable) {
            return [];
        }

        if ($rows === []) {
            return [];
        }

        $keys = ['mcp.tools', 'mcp.tool_handle', 'laravel/mcp', 'mcp.servers'];
        foreach ($rows as $row) {
            $keys[] = 'mcp.server.'.$row['name'];
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $mcpConfig
     */
    private static function shouldRegister(array $mcpConfig): bool
    {
        if (! (bool) ($mcpConfig['enabled'] ?? true)) {
            return false;
        }

        // Explicit opt-out: host may keep profiles for manual mounts only.
        if (array_key_exists('auto_register', $mcpConfig) && ! (bool) $mcpConfig['auto_register']) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $mcpConfig
     */
    private static function peerStatus(array $mcpConfig, ?PeerVersionProbe $probe): PeerSurfaceStatus
    {
        $probe ??= PeerVersionProbe::fake([PeerVersionProbe::PEER_MCP => true]);
        $bootstrap = new PeerSurfaceBootstrap($probe);

        return $bootstrap->evaluate('mcp', PeerVersionProbe::PEER_MCP, $mcpConfig);
    }

    /**
     * Build server rows from explicit servers config or profile keys (D-008).
     *
     * require_profile (default true): empty profiles / no servers → register nothing
     * (never dump the full catalog as one unscoped server).
     *
     * @param  array<string, mixed>  $mcpConfig
     * @return list<array{
     *     name: string,
     *     profile: string,
     *     path: string,
     *     source: string,
     *     middleware: list<string>
     * }>
     */
    private static function serverRows(array $mcpConfig): array
    {
        $prefix = self::normalizePrefix((string) ($mcpConfig['path_prefix'] ?? self::DEFAULT_PATH_PREFIX));
        $requireProfile = (bool) ($mcpConfig['require_profile'] ?? true);

        $explicit = $mcpConfig['servers'] ?? null;
        if (is_array($explicit) && $explicit !== []) {
            return self::rowsFromExplicitServers($explicit, $prefix);
        }

        $profiles = $mcpConfig['profiles'] ?? [];
        if (! is_array($profiles) || $profiles === []) {
            // D-008: no unscoped full-catalog server when profile is required (default).
            if ($requireProfile) {
                return [];
            }

            // require_profile=false still refuses a full dump — empty plan, loud policy elsewhere.
            return [];
        }

        $rows = [];
        foreach ($profiles as $name => $definition) {
            // Only associative profile names become servers (skip list-style keys).
            if (! is_string($name) || $name === '') {
                continue;
            }
            $rows[] = [
                'name' => $name,
                'profile' => $name,
                'path' => $prefix.'/'.$name,
                'source' => 'config',
                'middleware' => [],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, array{profile?: string, path?: string, middleware?: list<string>}|string>  $servers
     * @return list<array{
     *     name: string,
     *     profile: string,
     *     path: string,
     *     source: string,
     *     middleware: list<string>
     * }>
     */
    private static function rowsFromExplicitServers(array $servers, string $prefix): array
    {
        $rows = [];
        foreach ($servers as $name => $def) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            if (is_string($def)) {
                $profile = $def;
                $path = $prefix.'/'.$name;
                $middleware = [];
            } elseif (is_array($def)) {
                $profile = (string) ($def['profile'] ?? $name);
                $path = isset($def['path'])
                    ? self::normalizePath((string) $def['path'])
                    : $prefix.'/'.$name;
                $middleware = array_values(array_map('strval', (array) ($def['middleware'] ?? [])));
            } else {
                continue;
            }

            if ($profile === '') {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'profile' => $profile,
                'path' => $path,
                'source' => 'config',
                'middleware' => $middleware,
            ];
        }

        return $rows;
    }

    private static function normalizePrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        if ($prefix === '' || $prefix === '/') {
            return self::DEFAULT_PATH_PREFIX;
        }
        $prefix = '/'.ltrim($prefix, '/');

        return rtrim($prefix, '/');
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return self::DEFAULT_PATH_PREFIX;
        }

        return '/'.ltrim($path, '/');
    }
}
