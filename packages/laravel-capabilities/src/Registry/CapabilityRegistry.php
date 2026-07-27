<?php

namespace Rawphp\Capabilities\Registry;

use InvalidArgumentException;
use Rawphp\Capabilities\Discovery\AttributeDiscoverer;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Schema\InputValidator;
use Rawphp\Capabilities\Schema\OutputValidator;
use Rawphp\Capabilities\Schema\ToolSchemaExporter;
use Rawphp\Capabilities\Support\CapabilityResult;
use Throwable;

/**
 * Central choke point: definition store + invoke scaffolding for discovery/schema unit tests.
 *
 * Full pipeline stages (authz, approval, audit store) land in later REQs; this class
 * owns definition registration and the schema-facing invoke path used by D-014/D-017 tests.
 */
final class CapabilityRegistry
{
    /** @var array<string, CapabilityDefinition> */
    private array $definitions = [];

    /** @var array<string, string> alias => canonical name */
    private array $aliases = [];

    /** @var list<object> */
    private array $failedEvents = [];

    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $logs = [];

    /**
     * @param  array<string, bool>  $globallyEnabledSurfaces
     * @param  array{validate_output?: bool}  $validationConfig
     * @param  list<string>  $discoveryPaths
     */
    public function __construct(
        private array $globallyEnabledSurfaces = [
            'agent' => true,
            'mcp' => true,
            'http' => true,
            'cli' => true,
            'job' => true,
            'artisan' => true,
            'messaging' => false,
        ],
        private array $validationConfig = ['validate_output' => true],
        private array $discoveryPaths = [],
        private ?InputValidator $inputValidator = null,
        private ?OutputValidator $outputValidator = null,
    ) {
        $this->inputValidator ??= new InputValidator;
        $this->outputValidator ??= new OutputValidator;
    }

    public function define(string $name): CapabilityDefinitionBuilder
    {
        return CapabilityDefinitionBuilder::make($name);
    }

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

        $this->definitions[$definition->name] = $definition;

        foreach ($definition->aliases as $alias) {
            $this->aliases[$alias] = $definition->name;
        }
    }

    /**
     * Discover attributed classes under configured paths and register them.
     *
     * @param  list<string>  $paths
     * @param  list<class-string>  $classMap  Optional FQCN list (unit tests without filesystem scan)
     */
    public function discover(array $paths = [], array $classMap = []): void
    {
        $paths = $paths !== [] ? $paths : $this->discoveryPaths;
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

    /**
     * Resolve alias → canonical name before run (D-012).
     */
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
     * @param  array<string, bool>  $globallyEnabled
     */
    public function withGloballyEnabledSurfaces(array $globallyEnabled): self
    {
        $this->globallyEnabledSurfaces = $globallyEnabled;

        return $this;
    }

    /**
     * @return array<string, bool>
     */
    public function globallyEnabledSurfaces(): array
    {
        return $this->globallyEnabledSurfaces;
    }

    /**
     * @param  array{validate_output?: bool}  $config
     */
    public function withValidationConfig(array $config): self
    {
        $this->validationConfig = array_merge($this->validationConfig, $config);

        return $this;
    }

    public function validateOutputEnabled(): bool
    {
        return (bool) ($this->validationConfig['validate_output'] ?? true);
    }

    public function catalog(): CatalogPresenter
    {
        return new CatalogPresenter($this);
    }

    public function toolSchemas(): ToolSchemaExporter
    {
        return new ToolSchemaExporter($this);
    }

    /**
     * Minimal invoke used by schema/output unit tests (not full pipeline).
     *
     * @param  array<string, mixed>  $input
     * @param  array{caller?: string, skip_server_rules?: bool}  $options
     */
    public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
    {
        $definition = $this->get($nameOrAlias);
        $caller = $options['caller'] ?? 'http';

        try {
            $validated = $this->inputValidator->validate(
                $definition,
                $input,
                serverRules: ! ($options['skip_server_rules'] ?? false),
            );
        } catch (Throwable $e) {
            return CapabilityResult::failure(
                code: 'validation_failed',
                message: $e->getMessage(),
            );
        }

        if ($definition->run === null && $definition->handlerClass === null) {
            return CapabilityResult::failure(
                code: 'not_runnable',
                message: sprintf('Capability "%s" has no run handler.', $definition->name),
            );
        }

        try {
            $output = $this->executeRun($definition, $validated);
        } catch (Throwable $e) {
            $this->recordFailure($definition, $e->getMessage(), $caller);

            return CapabilityResult::failure(
                code: 'capability_failed',
                message: $e->getMessage(),
            );
        }

        if ($definition->shouldValidateOutput($this->validateOutputEnabled())) {
            $outputCheck = $this->outputValidator->validate($definition, $output);
            if ($outputCheck !== null) {
                $this->recordFailure($definition, $outputCheck->error['message'] ?? 'output_invalid', $caller);

                return $outputCheck;
            }
        }

        return CapabilityResult::success($output);
    }

    /**
     * @return list<object>
     */
    public function failedEvents(): array
    {
        return $this->failedEvents;
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function logs(): array
    {
        return $this->logs;
    }

    private function executeRun(CapabilityDefinition $definition, mixed $input): mixed
    {
        if (is_callable($definition->run)) {
            return ($definition->run)($input);
        }

        if ($definition->handlerClass !== null) {
            $handler = new ($definition->handlerClass);
            if (! method_exists($handler, 'run')) {
                throw new InvalidArgumentException(sprintf(
                    'Handler %s has no run() method.',
                    $definition->handlerClass,
                ));
            }

            return $handler->run($input);
        }

        throw new InvalidArgumentException('No run handler.');
    }

    private function recordFailure(CapabilityDefinition $definition, string $message, string $caller): void
    {
        $event = new \Rawphp\Capabilities\Events\CapabilityFailed(
            capability: $definition->name,
            code: 'output_invalid',
            message: $message,
            caller: $caller,
        );
        $this->failedEvents[] = $event;
        $this->logs[] = [
            'level' => 'error',
            'message' => $message,
            'context' => [
                'capability' => $definition->name,
                'code' => 'output_invalid',
                'caller' => $caller,
            ],
        ];
    }
}
