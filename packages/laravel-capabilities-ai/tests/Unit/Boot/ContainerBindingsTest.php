<?php

declare(strict_types=1);

/**
 * UR-017 / ORI-645: pure ContainerBindings plan (no Laravel app, no DB).
 */

use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;
use Rawphp\CapabilitiesAi\Domain\TurnClaim;
use Rawphp\CapabilitiesAi\Domain\TurnRunner;
use Rawphp\CapabilitiesAi\Support\AnthropicLlmClient;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\ContainerBindings;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;
use Rawphp\CapabilitiesAi\Support\RedisProgressStore;

function aiConfig(array $overrides = []): array
{
    $base = require dirname(__DIR__, 3).'/config/capabilities-ai.php';

    return array_replace_recursive($base, $overrides);
}

it('plan selects FakeLlmClient for driver=fake', function () {
    $resolved = ContainerBindings::resolve(aiConfig(['llm' => ['driver' => 'fake']]));

    expect($resolved['drivers']['llm']['resolved'])->toBe('fake')
        ->and($resolved['drivers']['llm']['concrete'])->toBe(FakeLlmClient::class)
        ->and($resolved['bindings'][LlmClient::class])->toBe(FakeLlmClient::class);
});

it('plan selects AnthropicLlmClient for driver=anthropic', function () {
    $resolved = ContainerBindings::resolve(aiConfig(['llm' => ['driver' => 'anthropic']]));

    expect($resolved['drivers']['llm']['resolved'])->toBe('anthropic')
        ->and($resolved['drivers']['llm']['concrete'])->toBe(AnthropicLlmClient::class)
        ->and($resolved['bindings'][LlmClient::class])->toBe(AnthropicLlmClient::class);
});

it('plan selects ArrayProgressStore for driver=array', function () {
    $resolved = ContainerBindings::resolve(aiConfig(['progress' => ['driver' => 'array']]));

    expect($resolved['drivers']['progress']['resolved'])->toBe('array')
        ->and($resolved['drivers']['progress']['concrete'])->toBe(ArrayProgressStore::class)
        ->and($resolved['bindings'][ProgressStore::class])->toBe(ArrayProgressStore::class);
});

it('plan selects RedisProgressStore for driver=redis', function () {
    $resolved = ContainerBindings::resolve(aiConfig(['progress' => ['driver' => 'redis']]));

    expect($resolved['drivers']['progress']['resolved'])->toBe('redis')
        ->and($resolved['drivers']['progress']['concrete'])->toBe(RedisProgressStore::class)
        ->and($resolved['bindings'][ProgressStore::class])->toBe(RedisProgressStore::class);
});

it('unknown llm.driver throws fail-closed', function () {
    ContainerBindings::resolve(aiConfig(['llm' => ['driver' => 'openai']]));
})->throws(InvalidArgumentException::class, 'Unknown llm.driver');

it('unknown progress.driver throws fail-closed', function () {
    ContainerBindings::resolve(aiConfig(['progress' => ['driver' => 'mysql']]));
})->throws(InvalidArgumentException::class, 'Unknown progress.driver');

it('plan includes TurnClaim TurnRunner ConversationService ProposalService', function () {
    $plan = ContainerBindings::plan(aiConfig());

    expect($plan)->toHaveKey(TurnClaim::class)
        ->and($plan)->toHaveKey(TurnRunner::class)
        ->and($plan)->toHaveKey(ConversationService::class)
        ->and($plan)->toHaveKey(ProposalService::class);
});

it('makeLlmClient returns FakeLlmClient for fake driver', function () {
    $client = ContainerBindings::makeLlmClient(aiConfig(['llm' => ['driver' => 'fake']]));

    expect($client)->toBeInstanceOf(FakeLlmClient::class)
        ->and($client)->toBeInstanceOf(LlmClient::class);
});

it('makeLlmClient returns AnthropicLlmClient for anthropic driver', function () {
    $client = ContainerBindings::makeLlmClient(aiConfig([
        'llm' => [
            'driver' => 'anthropic',
            'anthropic' => [
                'api_key' => 'test-key',
                'model' => 'claude-test',
                'base_url' => 'https://example.test',
            ],
        ],
    ]));

    expect($client)->toBeInstanceOf(AnthropicLlmClient::class);
});

it('makeProgressStore returns ArrayProgressStore for array driver', function () {
    $store = ContainerBindings::makeProgressStore(aiConfig(['progress' => ['driver' => 'array']]));

    expect($store)->toBeInstanceOf(ArrayProgressStore::class);
});

it('makeProgressStore redis without client fails closed', function () {
    ContainerBindings::makeProgressStore(aiConfig(['progress' => ['driver' => 'redis']]), null);
})->throws(RuntimeException::class, 'progress.driver=redis');

it('makeProgressStore redis with client builds RedisProgressStore', function () {
    $redis = new class
    {
        public function rPush(string $key, string $value): int
        {
            return 1;
        }

        public function lRange(string $key, int $start, int $end): array
        {
            return [];
        }
    };

    $store = ContainerBindings::makeProgressStore(
        aiConfig(['progress' => ['driver' => 'redis', 'redis_key_prefix' => 't:']]),
        $redis,
    );

    expect($store)->toBeInstanceOf(RedisProgressStore::class);
});

it('makeTurnRunner builds without host ContextProvider/ToolCatalog', function () {
    $runner = ContainerBindings::makeTurnRunner(
        claim: new TurnClaim,
        llm: new FakeLlmClient,
        progress: new ArrayProgressStore,
        config: aiConfig(['max_tool_rounds' => 3]),
    );

    expect($runner)->toBeInstanceOf(TurnRunner::class);
});

it('makeConversationService injects callable dispatch', function () {
    $calls = [];
    $dispatch = static function (object $job) use (&$calls): void {
        $calls[] = $job;
    };

    $service = ContainerBindings::makeConversationService($dispatch, new ArrayProgressStore);

    expect($service)->toBeInstanceOf(ConversationService::class);
});
