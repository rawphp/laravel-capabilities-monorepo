<?php

declare(strict_types=1);

/**
 * ORI-843 / Task 7: Phase 3 production guards for array progress + fake LLM.
 * Default closed outside testing unless CAPABILITIES_AI_ALLOW_UNSAFE is truthy.
 */

use Illuminate\Container\Container;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesAi\CapabilitiesAiServiceProvider;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\ContainerBindings;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;

/**
 * @param  array<string, mixed>  $items
 */
function udgConfigRepo(array $items): object
{
    return new class($items)
    {
        /** @param  array<string, mixed>  $items */
        public function __construct(private array $items) {}

        public function get(string $key, mixed $default = null): mixed
        {
            $parts = explode('.', $key);
            $cur = $this->items;
            foreach ($parts as $p) {
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
}

function udgFakeBus(): CapabilityBus
{
    return new class implements CapabilityBus
    {
        public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
        {
            return CapabilityResult::ok();
        }

        public function catalog(): CatalogPresenter
        {
            throw new RuntimeException('unused');
        }
    };
}

/**
 * Minimal app with optional environment() for production vs testing.
 *
 * @param  array<string, mixed>  $aiOverrides
 */
function bootUnsafeGuardContainer(array $aiOverrides = [], string $env = 'production'): Container
{
    $app = new class($env) extends Container
    {
        public function __construct(private string $envName)
        {
            // Illuminate\Container\Container has no required constructor args in some versions;
            // avoid parent::__construct() when it is private/unavailable.
        }

        public function runningInConsole(): bool
        {
            return true;
        }

        public function environment(string|array ...$environments): string|bool
        {
            if ($environments === []) {
                return $this->envName;
            }

            $targets = [];
            foreach ($environments as $item) {
                if (is_array($item)) {
                    foreach ($item as $e) {
                        $targets[] = (string) $e;
                    }
                } else {
                    $targets[] = (string) $item;
                }
            }

            return in_array($this->envName, $targets, true);
        }
    };

    $base = require dirname(__DIR__, 3).'/config/capabilities-ai.php';
    $config = array_replace_recursive($base, $aiOverrides);
    $app->instance('config', udgConfigRepo(['capabilities-ai' => $config]));
    $app->instance(CapabilityBus::class, udgFakeBus());

    (new CapabilitiesAiServiceProvider($app))->register();

    return $app;
}

/**
 * @return array{0: mixed, 1: mixed, 2: mixed}
 */
function udgClearAllowUnsafe(): array
{
    $prevEnv = $_ENV['CAPABILITIES_AI_ALLOW_UNSAFE'] ?? null;
    $prevServer = $_SERVER['CAPABILITIES_AI_ALLOW_UNSAFE'] ?? null;
    $prevGetenv = getenv('CAPABILITIES_AI_ALLOW_UNSAFE');

    unset($_ENV['CAPABILITIES_AI_ALLOW_UNSAFE'], $_SERVER['CAPABILITIES_AI_ALLOW_UNSAFE']);
    putenv('CAPABILITIES_AI_ALLOW_UNSAFE');

    return [$prevEnv, $prevServer, $prevGetenv];
}

/**
 * @param  array{0: mixed, 1: mixed, 2: mixed}  $prev
 */
function udgRestoreAllowUnsafe(array $prev): void
{
    [$prevEnv, $prevServer, $prevGetenv] = $prev;

    if ($prevEnv !== null) {
        $_ENV['CAPABILITIES_AI_ALLOW_UNSAFE'] = $prevEnv;
    } else {
        unset($_ENV['CAPABILITIES_AI_ALLOW_UNSAFE']);
    }

    if ($prevServer !== null) {
        $_SERVER['CAPABILITIES_AI_ALLOW_UNSAFE'] = $prevServer;
    } else {
        unset($_SERVER['CAPABILITIES_AI_ALLOW_UNSAFE']);
    }

    if ($prevGetenv === false || $prevGetenv === null) {
        putenv('CAPABILITIES_AI_ALLOW_UNSAFE');
    } else {
        putenv('CAPABILITIES_AI_ALLOW_UNSAFE='.$prevGetenv);
    }
}

it('assertSafeDrivers allows array and fake when isTesting', function () {
    expect(fn () => ContainerBindings::assertSafeDrivers(
        ['progress' => ['driver' => 'array'], 'llm' => ['driver' => 'fake']],
        isTesting: true,
        allowUnsafe: false,
    ))->not->toThrow(Throwable::class);
});

it('assertSafeDrivers allows array and fake when allowUnsafe', function () {
    expect(fn () => ContainerBindings::assertSafeDrivers(
        ['progress' => ['driver' => 'array'], 'llm' => ['driver' => 'fake']],
        isTesting: false,
        allowUnsafe: true,
    ))->not->toThrow(Throwable::class);
});

it('assertSafeDrivers throws on progress.driver=array outside testing', function () {
    expect(fn () => ContainerBindings::assertSafeDrivers(
        ['progress' => ['driver' => 'array'], 'llm' => ['driver' => 'anthropic']],
        isTesting: false,
        allowUnsafe: false,
    ))->toThrow(RuntimeException::class, 'progress.driver=array is not allowed outside testing');
});

it('assertSafeDrivers throws on llm.driver=fake outside testing', function () {
    expect(fn () => ContainerBindings::assertSafeDrivers(
        ['progress' => ['driver' => 'redis'], 'llm' => ['driver' => 'fake']],
        isTesting: false,
        allowUnsafe: false,
    ))->toThrow(RuntimeException::class, 'llm.driver=fake is not allowed outside testing');
});

it('assertSafeDrivers allows redis + anthropic outside testing', function () {
    expect(fn () => ContainerBindings::assertSafeDrivers(
        ['progress' => ['driver' => 'redis'], 'llm' => ['driver' => 'anthropic']],
        isTesting: false,
        allowUnsafe: false,
    ))->not->toThrow(Throwable::class);
});

it('SP resolve throws unsafe drivers outside testing without allow_unsafe', function () {
    $prev = udgClearAllowUnsafe();

    try {
        $app = bootUnsafeGuardContainer(
            [
                'progress' => ['driver' => 'array'],
                'llm' => ['driver' => 'fake'],
            ],
            env: 'production',
        );

        expect(fn () => $app->make(LlmClient::class))
            ->toThrow(RuntimeException::class);
    } finally {
        udgRestoreAllowUnsafe($prev);
    }
});

it('SP resolve allows unsafe drivers when CAPABILITIES_AI_ALLOW_UNSAFE=1', function () {
    $prev = udgClearAllowUnsafe();
    $_ENV['CAPABILITIES_AI_ALLOW_UNSAFE'] = '1';
    $_SERVER['CAPABILITIES_AI_ALLOW_UNSAFE'] = '1';
    putenv('CAPABILITIES_AI_ALLOW_UNSAFE=1');

    try {
        $app = bootUnsafeGuardContainer(
            [
                'progress' => ['driver' => 'array'],
                'llm' => ['driver' => 'fake'],
            ],
            env: 'production',
        );

        expect($app->make(LlmClient::class))->toBeInstanceOf(FakeLlmClient::class)
            ->and($app->make(ProgressStore::class))->toBeInstanceOf(ArrayProgressStore::class);
    } finally {
        udgRestoreAllowUnsafe($prev);
    }
});

it('SP resolve allows unsafe drivers when app environment is testing', function () {
    $prev = udgClearAllowUnsafe();

    try {
        $app = bootUnsafeGuardContainer(
            [
                'progress' => ['driver' => 'array'],
                'llm' => ['driver' => 'fake'],
            ],
            env: 'testing',
        );

        expect($app->make(LlmClient::class))->toBeInstanceOf(FakeLlmClient::class)
            ->and($app->make(ProgressStore::class))->toBeInstanceOf(ArrayProgressStore::class);
    } finally {
        udgRestoreAllowUnsafe($prev);
    }
});

it('host-prebound LlmClient and ProgressStore skip unsafe driver guards', function () {
    $prev = udgClearAllowUnsafe();

    try {
        $app = new class extends Container
        {
            public function runningInConsole(): bool
            {
                return true;
            }

            public function environment(string|array ...$environments): string|bool
            {
                if ($environments === []) {
                    return 'production';
                }

                $targets = [];
                foreach ($environments as $item) {
                    if (is_array($item)) {
                        foreach ($item as $e) {
                            $targets[] = (string) $e;
                        }
                    } else {
                        $targets[] = (string) $item;
                    }
                }

                return in_array('production', $targets, true);
            }
        };

        $hostLlm = new FakeLlmClient;
        $hostProgress = new ArrayProgressStore;
        $app->instance(LlmClient::class, $hostLlm);
        $app->instance(ProgressStore::class, $hostProgress);

        $base = require dirname(__DIR__, 3).'/config/capabilities-ai.php';
        $app->instance('config', udgConfigRepo([
            'capabilities-ai' => array_replace_recursive($base, [
                'progress' => ['driver' => 'array'],
                'llm' => ['driver' => 'fake'],
            ]),
        ]));
        $app->instance(CapabilityBus::class, udgFakeBus());

        (new CapabilitiesAiServiceProvider($app))->register();

        expect($app->make(LlmClient::class))->toBe($hostLlm)
            ->and($app->make(ProgressStore::class))->toBe($hostProgress);
    } finally {
        udgRestoreAllowUnsafe($prev);
    }
});
