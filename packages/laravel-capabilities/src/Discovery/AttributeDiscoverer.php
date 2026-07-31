<?php

namespace Rawphp\Capabilities\Discovery;

use Rawphp\Capabilities\Attributes\Capability as CapabilityAttribute;
use Rawphp\Capabilities\Contracts\DefinesCapability;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use ReflectionClass;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Canonical discovery: #[Capability] + DefinesCapability under configured path (D-017).
 */
final class AttributeDiscoverer
{
    /**
     * @param  list<string>  $paths  Absolute directories to scan for PHP files
     * @return list<CapabilityDefinition>
     */
    public function fromPaths(array $paths): array
    {
        $classes = [];
        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }
            $classes = array_merge($classes, $this->classesInPath($path));
        }

        return $this->fromClasses($classes);
    }

    /**
     * @param  list<class-string>  $classes
     * @return list<CapabilityDefinition>
     */
    public function fromClasses(array $classes): array
    {
        $definitions = [];
        foreach ($classes as $class) {
            if (! class_exists($class)) {
                continue;
            }

            $definition = $this->fromClass($class);
            if ($definition !== null) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * @param  class-string  $class
     */
    public function fromClass(string $class): ?CapabilityDefinition
    {
        $reflection = new ReflectionClass($class);
        if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
            return null;
        }

        $attributes = $reflection->getAttributes(CapabilityAttribute::class);
        if ($attributes === []) {
            return null;
        }

        if (! $reflection->implementsInterface(DefinesCapability::class)) {
            return null;
        }

        /** @var CapabilityAttribute $attr */
        $attr = $attributes[0]->newInstance();

        return new CapabilityDefinition(
            name: $attr->name,
            description: $attr->description,
            surfaces: $attr->surfaces,
            input: $attr->input,
            output: $attr->output,
            aliases: $attr->aliases,
            deprecated: $attr->deprecated,
            successor: $attr->successor,
            sunset_at: $attr->sunset_at,
            groups: $attr->groups,
            tags: $attr->tags,
            readOnly: $attr->readOnly,
            allowSystemCallers: $attr->allowSystemCallers,
            globalSystem: $attr->globalSystem,
            approvalPolicy: $attr->approvalPolicy,
            approvalTtlHours: $attr->approvalTtlHours,
            rateLimit: $attr->rateLimit,
            idempotent: CapabilityDefinition::normalizeIdempotent($attr->idempotent),
            audit: $attr->audit,
            handlerClass: $class,
            authorize: null,
            run: null,
            source: 'attribute',
            cliDomain: $attr->cliDomain,
            cliVerb: $attr->cliVerb,
        );
    }

    /**
     * Default discovery path when app_path is available; unit tests pass explicit paths.
     */
    public static function defaultPath(): string
    {
        if (function_exists('app_path')) {
            return app_path('Capabilities');
        }

        return 'app/Capabilities';
    }

    /**
     * @return list<class-string>
     */
    private function classesInPath(string $path): array
    {
        $classes = [];
        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)),
            '/^.+\.php$/i',
            RegexIterator::GET_MATCH,
        );

        foreach ($iterator as $file) {
            $filePath = is_array($file) ? $file[0] : (string) $file;
            if (! is_string($filePath) || ! is_file($filePath)) {
                continue;
            }
            $declared = $this->classFromFile($filePath);
            if ($declared !== null) {
                $classes[] = $declared;
            }
        }

        return $classes;
    }

    /**
     * @return class-string|null
     */
    private function classFromFile(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return null;
        }

        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $contents, $m)) {
            $namespace = trim($m[1]);
        }

        if (! preg_match('/\bclass\s+(\w+)/', $contents, $m)) {
            return null;
        }

        $class = $m[1];
        $fqcn = $namespace !== null ? $namespace.'\\'.$class : $class;

        // Ensure file is loaded for reflection.
        if (! class_exists($fqcn, false)) {
            require_once $filePath;
        }

        return class_exists($fqcn) ? $fqcn : null;
    }
}
