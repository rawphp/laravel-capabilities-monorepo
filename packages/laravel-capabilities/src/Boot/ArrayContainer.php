<?php

namespace Rawphp\Capabilities\Boot;

/**
 * Minimal container for unit-testing rebind semantics (BOOT-001).
 *
 * Not a Laravel container — only used in package unit tests / binding plan demos.
 */
final class ArrayContainer
{
    /** @var array<string, mixed> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * @param  array<string, mixed>  $bindings
     */
    public function __construct(array $bindings = [])
    {
        foreach ($bindings as $abstract => $concrete) {
            $this->bind($abstract, $concrete);
        }
    }

    public static function fromPlan(): self
    {
        return new self(ContainerBindings::plan());
    }

    public function bind(string $abstract, mixed $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        unset($this->instances[$abstract]);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
        $this->bindings[$abstract] = $instance;
    }

    public function bound(string $abstract): bool
    {
        return array_key_exists($abstract, $this->bindings) || array_key_exists($abstract, $this->instances);
    }

    public function get(string $abstract): mixed
    {
        if (array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        if (! array_key_exists($abstract, $this->bindings)) {
            throw new \RuntimeException("Abstract [{$abstract}] is not bound.");
        }

        return $this->bindings[$abstract];
    }

    /**
     * @return array<string, mixed>
     */
    public function bindings(): array
    {
        return $this->bindings;
    }
}
