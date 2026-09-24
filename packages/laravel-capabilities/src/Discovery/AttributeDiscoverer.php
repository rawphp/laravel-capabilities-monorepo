<?php

namespace Rawphp\Capabilities\Discovery;

use Rawphp\Capabilities\Attributes\Capability as CapabilityAttribute;
use Rawphp\Capabilities\Boot\BootException;
use Rawphp\Capabilities\Contracts\DefinesCapability;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
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

        /** @var CapabilityAttribute $attr */
        $attr = $attributes[0]->newInstance();

        if (! $reflection->implementsInterface(DefinesCapability::class)) {
            throw BootException::capabilityMissingContract($attr->name, $class);
        }

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
            $classes = array_merge($classes, $this->classesFromFile($filePath));
        }

        return $classes;
    }

    /**
     * Every named class declared in the file, parsed from tokens so docblocks,
     * `Foo::class`, anonymous classes and a leading base class do not hide it.
     *
     * @return list<class-string>
     */
    private function classesFromFile(string $filePath): array
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return [];
        }

        $tokens = array_values(array_filter(
            token_get_all($contents),
            fn ($token) => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));

        $namespace = '';
        $declared = [];
        foreach ($tokens as $i => $token) {
            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $next = $tokens[$i + 1] ?? null;
                $namespace = is_array($next) && in_array($next[0], [T_STRING, T_NAME_QUALIFIED], true) ? $next[1].'\\' : '';

                continue;
            }

            $name = $tokens[$i + 1] ?? null;
            if ($token[0] === T_CLASS && is_array($name) && $name[0] === T_STRING) {
                $declared[] = $namespace.$name[1];
            }
        }

        if ($declared === []) {
            return [];
        }

        // Ensure file is loaded for reflection (unless the autoloader already included it).
        $loaded = array_filter($declared, fn (string $class) => class_exists($class, false));
        if ($loaded === []) {
            require_once $filePath;
        }

        return array_values(array_filter($declared, fn (string $class) => class_exists($class)));
    }
}
