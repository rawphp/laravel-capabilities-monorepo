<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use ArrayAccess;
use Rawphp\Capabilities\CapabilitiesServiceProvider;
use ReflectionClass;

/**
 * Minimal Laravel-shaped container for exercising CapabilitiesServiceProvider::register()
 * without an Illuminate Application (singleton caching, aliases, ArrayAccess, `bound`).
 */
final class FakeProviderApp implements ArrayAccess
{
    /** @var array<string, mixed> */
    public array $singletons = [];

    /** @var array<string, mixed> */
    public array $resolved = [];

    /** @var array<string, string> */
    public array $aliases = [];

    /** @var array<string, list<callable>> */
    public array $afterResolving = [];

    public object $config;

    /**
     * @param  array<string, mixed>  $capabilitiesConfig
     */
    public function __construct(array $capabilitiesConfig = [])
    {
        $this->config = new class
        {
            /** @var array<string, mixed> */
            public array $items = [];

            public function get(string $key, mixed $default = null): mixed
            {
                $cur = $this->items;
                foreach (explode('.', $key) as $p) {
                    if (! is_array($cur) || ! array_key_exists($p, $cur)) {
                        return $default;
                    }
                    $cur = $cur[$p];
                }

                return $cur;
            }

            public function set(string $key, mixed $value): void
            {
                $this->items[$key] = $value;
            }
        };
        if ($capabilitiesConfig !== []) {
            $this->config->set('capabilities', $capabilitiesConfig);
        }
    }

    /**
     * Register the real provider (config merge + publishes stubbed) and return the app.
     *
     * @param  array<string, mixed>  $capabilitiesConfig
     * @param  array<string, mixed>  $instances  abstract => instance bound before register()
     * @param  self|null  $app  pre-built app (instances / tags already bound) to register into
     */
    public static function registered(array $capabilitiesConfig = [], array $instances = [], ?self $app = null): self
    {
        $app ??= new self;
        if ($capabilitiesConfig !== []) {
            $app->config->set('capabilities', $capabilitiesConfig);
        }
        foreach ($instances as $abstract => $instance) {
            $app->instance($abstract, $instance);
        }

        $provider = new class($app) extends CapabilitiesServiceProvider
        {
            public function __construct(public object $fakeApp)
            {
                $prop = (new ReflectionClass(CapabilitiesServiceProvider::class))->getParentClass()->getProperty('app');
                $prop->setValue($this, $fakeApp);
            }

            protected function publishes(array $paths, $group = null): void {}

            protected function mergeConfigFrom($path, $key): void
            {
                $config = $this->fakeApp->make('config');
                $existing = $config->get($key, []);
                $config->set($key, array_replace_recursive(require $path, is_array($existing) ? $existing : []));
            }
        };
        $provider->register();
        $app->provider = $provider;

        return $app;
    }

    public ?CapabilitiesServiceProvider $provider = null;

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->singletons[$abstract] = $concrete;
        unset($this->resolved[$abstract]);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        unset($this->aliases[$abstract]);
        $this->singletons[$abstract] = $instance;
        $this->resolved[$abstract] = $instance;
    }

    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    public function afterResolving(string $abstract, callable $callback): void
    {
        $this->afterResolving[$abstract][] = $callback;
    }

    public function bound(string $abstract): bool
    {
        return $this->offsetExists($abstract);
    }

    /** @var array<string, list<string>> */
    public array $tags = [];

    /**
     * @param  list<string>|string  $abstracts
     */
    public function tag(array|string $abstracts, string $tag): void
    {
        foreach ((array) $abstracts as $abstract) {
            $this->tags[$tag][] = $abstract;
        }
    }

    /**
     * @return list<mixed>
     */
    public function tagged(string $tag): iterable
    {
        return array_map(fn (string $abstract): mixed => $this->make($abstract), $this->tags[$tag] ?? []);
    }

    public function make(string $abstract): mixed
    {
        if ($abstract === 'config') {
            return $this->config;
        }

        $seen = [];
        while (isset($this->aliases[$abstract]) && ! isset($seen[$abstract])) {
            $seen[$abstract] = true;
            $abstract = $this->aliases[$abstract];
        }

        if (array_key_exists($abstract, $this->resolved)) {
            return $this->resolved[$abstract];
        }
        if (! array_key_exists($abstract, $this->singletons)) {
            throw new \RuntimeException("Binding not found: {$abstract}");
        }
        $entry = $this->singletons[$abstract];
        if (is_callable($entry)) {
            $this->resolved[$abstract] = $entry($this);

            return $this->resolved[$abstract];
        }

        return $entry;
    }

    public function offsetGet(mixed $key): mixed
    {
        return $this->make((string) $key);
    }

    public function offsetExists(mixed $key): bool
    {
        $abstract = (string) $key;
        if ($abstract === 'config' || isset($this->singletons[$abstract]) || isset($this->resolved[$abstract])) {
            return true;
        }
        $seen = [];
        while (isset($this->aliases[$abstract]) && ! isset($seen[$abstract])) {
            $seen[$abstract] = true;
            $abstract = $this->aliases[$abstract];
            if (isset($this->singletons[$abstract]) || isset($this->resolved[$abstract])) {
                return true;
            }
        }

        return false;
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        $this->singletons[(string) $key] = $value;
    }

    public function offsetUnset(mixed $key): void
    {
        unset($this->singletons[$key], $this->resolved[$key]);
    }

    public function runningInConsole(): bool
    {
        return false;
    }

    public function configurationIsCached(): bool
    {
        return false;
    }
}
