<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\TurnCapacityExceededException;
use Rawphp\CapabilitiesAi\Domain\TurnRateLimitedException;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\ContainerBindings;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryConversationStore;

/**
 * @return array{0: ConversationService, 1: object, 2: InMemoryConversationStore}
 */
function turnCapacityService(int $maxConcurrentTurns): array
{
    $bag = new class
    {
        /** @var list<object> */
        public array $jobs = [];
    };
    $dispatch = static function (object $job) use ($bag): void {
        $bag->jobs[] = $job;
    };
    $store = new InMemoryConversationStore;

    return [
        new ConversationService($dispatch, new ArrayProgressStore, maxConcurrentTurns: $maxConcurrentTurns, store: $store),
        $bag,
        $store,
    ];
}

/**
 * @return array{0: ConversationService, 1: InMemoryConversationStore}
 */
function turnRateService(?InMemoryRateLimiter $limiter, int $turnsPerMinute): array
{
    $store = new InMemoryConversationStore;

    return [
        new ConversationService(
            static fn ($j) => null,
            new ArrayProgressStore,
            turnLimiter: $limiter,
            turnsPerMinute: $turnsPerMinute,
            store: $store,
        ),
        $store,
    ];
}

it('does not cap active turns when maxConcurrentTurns is 0 (default)', function () {
    [$service, $bag] = turnCapacityService(0);

    $service->createUserMessage('a');
    $service->createUserMessage('b');
    $service->createUserMessage('c');

    expect($bag->jobs)->toHaveCount(3);
});

it('refuses a new turn at the ceiling without persisting or dispatching', function () {
    [$service, $bag, $store] = turnCapacityService(2);
    $first = $service->createUserMessage('a');
    $service->createUserMessage('b', $first['conversation_ulid']);

    try {
        $service->createUserMessage('c');
        $this->fail('expected TurnCapacityExceededException');
    } catch (TurnCapacityExceededException $e) {
        expect($e->limit)->toBe(2)
            ->and($e->getMessage())->toContain('retry later');
    }

    expect($bag->jobs)->toHaveCount(2)
        ->and($store->turns)->toHaveCount(2)
        ->and($store->messages)->toHaveCount(2)
        ->and($store->conversations)->toHaveCount(1);
});

it('counts running turns and frees capacity once turns finish', function () {
    [$service, $bag, $store] = turnCapacityService(1);
    $ids = $service->createUserMessage('a');
    $store->turn($ids['turn_ulid'])->status = Turn::STATUS_RUNNING;

    expect(fn () => $service->createUserMessage('b'))->toThrow(TurnCapacityExceededException::class);

    foreach ([Turn::STATUS_COMPLETED, Turn::STATUS_FAILED, Turn::STATUS_CANCELLED] as $status) {
        foreach ($store->turns as $turn) {
            if (in_array($turn->status, [Turn::STATUS_QUEUED, Turn::STATUS_RUNNING], true)) {
                $turn->status = $status;
            }
        }
        $service->createUserMessage("after {$status}");
    }

    expect($bag->jobs)->toHaveCount(4);
});

it('rejects a negative maxConcurrentTurns', function () {
    new ConversationService(static fn ($j) => null, new ArrayProgressStore, maxConcurrentTurns: -1);
})->throws(InvalidArgumentException::class);

it('makeConversationService wires max_concurrent_turns from config', function () {
    $service = ContainerBindings::makeConversationService(
        static fn ($j) => null,
        new ArrayProgressStore,
        config: ['max_concurrent_turns' => 1],
        store: new InMemoryConversationStore,
    );
    $service->createUserMessage('a');

    expect(fn () => $service->createUserMessage('b'))->toThrow(TurnCapacityExceededException::class);
});

it('maxConcurrentTurnsFromConfig defaults to 0 and clamps invalid values', function () {
    expect(ContainerBindings::maxConcurrentTurnsFromConfig([]))->toBe(0)
        ->and(ContainerBindings::maxConcurrentTurnsFromConfig(['max_concurrent_turns' => 5]))->toBe(5)
        ->and(ContainerBindings::maxConcurrentTurnsFromConfig(['max_concurrent_turns' => '3']))->toBe(3)
        ->and(ContainerBindings::maxConcurrentTurnsFromConfig(['max_concurrent_turns' => -2]))->toBe(0)
        ->and(ContainerBindings::maxConcurrentTurnsFromConfig(['max_concurrent_turns' => 'lots']))->toBe(0);
});

it('package config ships max_concurrent_turns off by default', function () {
    $config = require dirname(__DIR__, 3).'/config/capabilities-ai.php';

    expect($config)->toHaveKey('max_concurrent_turns')
        ->and($config['max_concurrent_turns'])->toBe(0);
});

it('refuses a user over turns_per_minute before anything is persisted or dispatched (D-013)', function () {
    $limiter = new InMemoryRateLimiter;
    $limiter->hit('rl:ai:user:u1', 60);
    $limiter->hit('rl:ai:user:u1', 60);
    $jobs = [];
    $store = new InMemoryConversationStore;
    $service = new ConversationService(
        static function (object $job) use (&$jobs): void {
            $jobs[] = $job;
        },
        new ArrayProgressStore,
        turnLimiter: $limiter,
        turnsPerMinute: 2,
        store: $store,
    );

    try {
        $service->createUserMessage('flood', userId: 'u1');
        $this->fail('expected TurnRateLimitedException');
    } catch (TurnRateLimitedException $e) {
        expect($e->limit)->toBe(2)
            ->and($e->getMessage())->toContain('retry later');
    }

    expect($jobs)->toBe([])
        ->and($store->conversations)->toBe([])
        ->and($limiter->remaining('rl:ai:user:u1', 2))->toBe(0);
});

it('counts one turn per accepted message per user and leaves other users alone', function () {
    [$service, $store] = turnRateService(new InMemoryRateLimiter, 1);

    $service->createUserMessage('first', userId: 'u1');
    $service->createUserMessage('other user', userId: 'u2');

    expect(fn () => $service->createUserMessage('second', userId: 'u1'))->toThrow(TurnRateLimitedException::class)
        ->and($store->turns)->toHaveCount(2);
});

it('does not rate-limit when turnsPerMinute is 0 or no limiter or no user is given', function () {
    $limiter = new InMemoryRateLimiter;
    $created = 0;
    foreach ([turnRateService($limiter, 0), turnRateService(null, 1)] as [$service, $store]) {
        $service->createUserMessage('a', userId: 'u1');
        $service->createUserMessage('b', userId: 'u1');
        $created += count($store->turns);
    }
    [$limited, $store] = turnRateService($limiter, 1);
    $limited->createUserMessage('server-side, no user');
    $limited->createUserMessage('server-side, no user');

    expect($created + count($store->turns))->toBe(6);
});

it('rejects a negative turnsPerMinute', function () {
    new ConversationService(static fn ($j) => null, new ArrayProgressStore, turnsPerMinute: -1);
})->throws(InvalidArgumentException::class);

it('makeConversationService wires turns_per_minute and the core RateLimiter', function () {
    $limiter = new InMemoryRateLimiter;
    $limiter->hit('rl:ai:user:u9', 60);
    $service = ContainerBindings::makeConversationService(
        static fn ($j) => null,
        new ArrayProgressStore,
        config: ['turns_per_minute' => 1],
        turnLimiter: $limiter,
        store: new InMemoryConversationStore,
    );

    expect(fn () => $service->createUserMessage('x', userId: 'u9'))->toThrow(TurnRateLimitedException::class)
        ->and(ContainerBindings::turnsPerMinuteFromConfig([]))->toBe(ConversationService::DEFAULT_TURNS_PER_MINUTE)
        ->and(ContainerBindings::turnsPerMinuteFromConfig(['turns_per_minute' => '5']))->toBe(5)
        ->and(ContainerBindings::turnsPerMinuteFromConfig(['turns_per_minute' => -3]))->toBe(0)
        ->and(ContainerBindings::turnsPerMinuteFromConfig(['turns_per_minute' => 'lots']))->toBe(ConversationService::DEFAULT_TURNS_PER_MINUTE);
});

it('package config ships turns_per_minute on by default', function () {
    $config = require dirname(__DIR__, 3).'/config/capabilities-ai.php';

    expect($config['turns_per_minute'])->toBe(ConversationService::DEFAULT_TURNS_PER_MINUTE)
        ->and(ConversationService::DEFAULT_TURNS_PER_MINUTE)->toBeGreaterThan(0);
});
