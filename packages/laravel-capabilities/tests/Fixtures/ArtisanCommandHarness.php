<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Runs an Illuminate console command end-to-end (signature parse → handle → output)
 * on a bare container: no Application, no database, no kernel.
 */
final class ArtisanCommandHarness
{
    /**
     * @param  array<string, mixed>  $input  argument/option bag, e.g. ['name' => 'x', '--input' => '{}']
     * @param  array<string, mixed>  $bindings  abstract => instance (a Closure is bound as a factory)
     * @param  array<string, mixed>|null  $config  root config tree bound as `config` (null = unbound)
     * @return array{exit: int, output: string}
     */
    public static function run(Command $command, array $input = [], array $bindings = [], ?array $config = null): array
    {
        $container = new class extends Container
        {
            public function runningUnitTests(): bool
            {
                return true;
            }
        };
        foreach ($bindings as $abstract => $instance) {
            $instance instanceof Closure
                ? $container->bind($abstract, $instance)
                : $container->instance($abstract, $instance);
        }
        if ($config !== null) {
            $container->instance('config', self::config($config));
        }

        $command->setLaravel($container);
        $output = new BufferedOutput;
        $exit = $command->run(new ArrayInput($input), $output);

        return ['exit' => $exit, 'output' => $output->fetch()];
    }

    /**
     * @param  array<string, mixed>  $tree
     */
    public static function config(array $tree): object
    {
        return new class($tree)
        {
            /** @param  array<string, mixed>  $tree */
            public function __construct(private array $tree) {}

            public function has(string $key): bool
            {
                return $this->get($key, $this) !== $this;
            }

            public function get(string $key, mixed $default = null): mixed
            {
                $v = $this->tree;
                foreach (explode('.', $key) as $part) {
                    if (! is_array($v) || ! array_key_exists($part, $v)) {
                        return $default;
                    }
                    $v = $v[$part];
                }

                return $v;
            }
        };
    }
}
