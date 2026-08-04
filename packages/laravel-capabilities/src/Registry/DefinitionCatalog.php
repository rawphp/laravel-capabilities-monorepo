<?php

namespace Rawphp\Capabilities\Registry;

use InvalidArgumentException;
use Rawphp\Capabilities\Discovery\AttributeDiscoverer;

/**
 * In-memory store of capability definitions, aliases, and CLI (domain, verb) routes.
 *
 * Extracted from {@see CapabilityRegistry} so registration/lookup stays independent of the invoke pipeline.
 */
final class DefinitionCatalog
{
    /** @var array<string, CapabilityDefinition> */
    private array $definitions = [];

    /** @var array<string, string> alias => canonical name */
    private array $aliases = [];

    /** @var array<string, string> "domain\\0verb" => canonical capability name (CLI-002) */
    private array $cliRoutes = [];

    public function register(CapabilityDefinition $definition): void
    {
        if (isset($this->definitions[$definition->name])) {
            throw new InvalidArgumentException(sprintf(
                'Duplicate capability name "%s" at registration (D-017).',
                $definition->name,
            ));
        }

        foreach ($definition->aliases as $alias) {
            if (isset($this->definitions[$alias]) || isset($this->aliases[$alias])) {
                throw new InvalidArgumentException(sprintf(
                    'Alias "%s" collides with an existing capability name or alias.',
                    $alias,
                ));
            }
        }

        $this->assertUniqueCliPair($definition);

        $this->definitions[$definition->name] = $definition;

        foreach ($definition->aliases as $alias) {
            $this->aliases[$alias] = $definition->name;
        }

        if ($definition->cliDomain !== null && $definition->cliVerb !== null) {
            $this->cliRoutes[$this->cliRouteKey($definition->cliDomain, $definition->cliVerb)] = $definition->name;
        }
    }

    /**
     * @param  list<string>  $paths  Explicit discovery paths; when empty, `$defaultPaths` is used
     * @param  list<class-string>  $classMap  When non-empty, discover from classes instead of paths
     * @param  list<string>  $defaultPaths  Fallback paths when `$paths` is empty
     */
    public function discover(array $paths = [], array $classMap = [], array $defaultPaths = []): void
    {
        $paths = $paths !== [] ? $paths : $defaultPaths;
        $discoverer = new AttributeDiscoverer;
        $definitions = $classMap !== []
            ? $discoverer->fromClasses($classMap)
            : $discoverer->fromPaths($paths);

        foreach ($definitions as $definition) {
            $this->register($definition);
        }
    }

    public function has(string $nameOrAlias): bool
    {
        return $this->resolveName($nameOrAlias) !== null;
    }

    public function get(string $nameOrAlias): CapabilityDefinition
    {
        $name = $this->resolveName($nameOrAlias);
        if ($name === null) {
            throw new InvalidArgumentException(sprintf('Unknown capability "%s".', $nameOrAlias));
        }

        return $this->definitions[$name];
    }

    public function resolveName(string $nameOrAlias): ?string
    {
        if (isset($this->definitions[$nameOrAlias])) {
            return $nameOrAlias;
        }

        return $this->aliases[$nameOrAlias] ?? null;
    }

    /**
     * @return array<string, CapabilityDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return list<CapabilityDefinition>
     */
    public function definitions(): array
    {
        return array_values($this->definitions);
    }

    /**
     * Reject two definitions claiming the same CLI (domain, verb) pair (server authoritative).
     *
     * @throws InvalidArgumentException
     */
    private function assertUniqueCliPair(CapabilityDefinition $definition): void
    {
        if ($definition->cliDomain === null || $definition->cliVerb === null) {
            return;
        }

        $key = $this->cliRouteKey($definition->cliDomain, $definition->cliVerb);
        if (! isset($this->cliRoutes[$key])) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Duplicate CLI routing pair (domain="%s", verb="%s"): capability "%s" collides with already-registered "%s".',
            $definition->cliDomain,
            $definition->cliVerb,
            $definition->name,
            $this->cliRoutes[$key],
        ));
    }

    private function cliRouteKey(string $domain, string $verb): string
    {
        return $domain."\0".$verb;
    }
}
