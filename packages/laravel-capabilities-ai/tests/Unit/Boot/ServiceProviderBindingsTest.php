<?php

declare(strict_types=1);

/**
 * UR-017 / ORI-645: ServiceProvider registers resolvable factories on a minimal container.
 * No full Laravel HTTP/DB app — Illuminate Container + config repository only.
 */

use Illuminate\Container\Container;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesAi\CapabilitiesAiServiceProvider;
use Rawphp\CapabilitiesAi\Contracts\ConversationContextProvider;
use Rawphp\CapabilitiesAi\Contracts\IdempotencyReadiness;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;
use Rawphp\CapabilitiesAi\Domain\TurnClaim;
use Rawphp\CapabilitiesAi\Domain\TurnRunner;
use Rawphp\CapabilitiesAi\Support\AlwaysReadyIdempotency;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;

function aiFakeBus(): CapabilityBus
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
 * Minimal config repository (no illuminate/config package required).
 *
 * @param  array<string, mixed>  $items
 */
function aiConfigRepo(array $items): object
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

/**
 * Minimal Application stand-in that satisfies ServiceProvider constructor + register().
 */
function bootAiProviderContainer(array $configOverrides = []): Container
{
    $app = new class extends Container
    {
        public function runningInConsole(): bool
        {
            return true;
        }
    };

    $base = require dirname(__DIR__, 3).'/config/capabilities-ai.php';
    $config = array_replace_recursive($base, $configOverrides);
    $app->instance('config', aiConfigRepo([
        'capabilities-ai' => $config,
    ]));

    $app->instance(CapabilityBus::class, aiFakeBus());

    $provider = new CapabilitiesAiServiceProvider($app);
    $provider->register();

    return $app;
}

it('resolves TurnRunner from container with fake driver', function () {
    $app = bootAiProviderContainer(['llm' => ['driver' => 'fake']]);

    $runner = $app->make(TurnRunner::class);

    expect($runner)->toBeInstanceOf(TurnRunner::class)
        ->and($app->make(LlmClient::class))->toBeInstanceOf(FakeLlmClient::class)
        ->and($app->make(ProgressStore::class))->toBeInstanceOf(ArrayProgressStore::class)
        ->and($app->make(TurnClaim::class))->toBeInstanceOf(TurnClaim::class);
});

it('resolves ConversationService with callable dispatch', function () {
    $app = bootAiProviderContainer();

    $service = $app->make(ConversationService::class);

    expect($service)->toBeInstanceOf(ConversationService::class);
});

it('resolves ProposalService with CapabilityBus', function () {
    $app = bootAiProviderContainer();

    $service = $app->make(ProposalService::class);

    expect($service)->toBeInstanceOf(ProposalService::class);
});

it('defaults IdempotencyReadiness to AlwaysReadyIdempotency', function () {
    $app = bootAiProviderContainer();

    expect($app->make(IdempotencyReadiness::class))->toBeInstanceOf(AlwaysReadyIdempotency::class)
        ->and($app->make(IdempotencyReadiness::class)->isReady())->toBeTrue();
});

it('does not overwrite host-prebound IdempotencyReadiness', function () {
    $app = new class extends Container
    {
        public function runningInConsole(): bool
        {
            return true;
        }
    };

    $host = new class implements IdempotencyReadiness
    {
        public function isReady(): bool
        {
            return false;
        }
    };
    $app->instance(IdempotencyReadiness::class, $host);

    $base = require dirname(__DIR__, 3).'/config/capabilities-ai.php';
    $app->instance('config', aiConfigRepo(['capabilities-ai' => $base]));
    $app->instance(CapabilityBus::class, aiFakeBus());

    (new CapabilitiesAiServiceProvider($app))->register();

    expect($app->make(IdempotencyReadiness::class))->toBe($host)
        ->and($app->make(IdempotencyReadiness::class)->isReady())->toBeFalse();
});

it('does not overwrite host-prebound LlmClient', function () {
    $app = new class extends Container
    {
        public function runningInConsole(): bool
        {
            return true;
        }
    };

    $hostLlm = new FakeLlmClient;
    $app->instance(LlmClient::class, $hostLlm);

    $base = require dirname(__DIR__, 3).'/config/capabilities-ai.php';
    $app->instance('config', aiConfigRepo([
        'capabilities-ai' => array_replace_recursive($base, ['llm' => ['driver' => 'anthropic']]),
    ]));
    $app->instance(CapabilityBus::class, aiFakeBus());

    (new CapabilitiesAiServiceProvider($app))->register();

    expect($app->make(LlmClient::class))->toBe($hostLlm);
});

it('does not overwrite host-prebound ProgressStore', function () {
    $app = new class extends Container
    {
        public function runningInConsole(): bool
        {
            return true;
        }
    };

    $hostStore = new ArrayProgressStore;
    $app->instance(ProgressStore::class, $hostStore);

    $base = require dirname(__DIR__, 3).'/config/capabilities-ai.php';
    $app->instance('config', aiConfigRepo([
        'capabilities-ai' => array_replace_recursive($base, ['progress' => ['driver' => 'redis']]),
    ]));
    $app->instance(CapabilityBus::class, aiFakeBus());

    (new CapabilitiesAiServiceProvider($app))->register();

    expect($app->make(ProgressStore::class))->toBe($hostStore);
});

it('does not bind ConversationContextProvider or ToolCatalog by default', function () {
    $app = bootAiProviderContainer();

    expect($app->bound(ConversationContextProvider::class))->toBeFalse()
        ->and($app->bound(ToolCatalog::class))->toBeFalse();
});

it('TurnRunner run fails closed without host ContextProvider/ToolCatalog', function () {
    $app = bootAiProviderContainer();
    $runner = $app->make(TurnRunner::class);

    $runner->run('01FAKEULIDTURN000000000000');
})->throws(RuntimeException::class, 'ConversationContextProvider and ToolCatalog must be bound');

it('ServiceProvider source still mergeConfig and publish tags', function () {
    $path = dirname(__DIR__, 3).'/src/CapabilitiesAiServiceProvider.php';
    $src = file_get_contents($path) ?: '';

    expect($src)->toContain('mergeConfigFrom')
        ->and($src)->toContain('registerPackageBindings')
        ->and($src)->toContain('LlmClient::class')
        ->and($src)->toContain('TurnRunner::class')
        ->and($src)->not->toContain('handle(TurnRunner'); // no RunTurnJob body work
});

it('RunTurnJob handle type-hints TurnRunner (UR-021 wiring allowed)', function () {
    $path = dirname(__DIR__, 3).'/src/Jobs/RunTurnJob.php';
    $src = file_get_contents($path) ?: '';

    // UR-017 only required that DI not own the job body; UR-021 wires handle(TurnRunner).
    expect($src)->toContain('handle(TurnRunner $runner)')
        ->and($src)->toContain('$runner->run($this->turnUlid)');
});
