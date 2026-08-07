<?php

declare(strict_types=1);

use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\RedisProgressStore;

it('ArrayProgressStore implements ProgressStore', function () {
    expect(new ArrayProgressStore)->toBeInstanceOf(ProgressStore::class);
});

it('append and since return ordered events after cursor', function () {
    $store = new ArrayProgressStore;
    $turn = '01TESTTURNULID000000000000';

    $store->append($turn, ['kind' => 'status', 'data' => ['status' => 'running']]);
    $store->append($turn, ['kind' => 'token', 'data' => ['text' => 'hi']]);
    $store->append($turn, ['kind' => 'tool', 'data' => ['name' => 'x']]);
    $store->append($turn, ['kind' => 'error', 'data' => ['msg' => 'nope']]);
    $store->append($turn, ['kind' => 'terminal', 'data' => ['status' => 'completed']]);

    $all = $store->since($turn, 0);
    expect($all)->toHaveCount(5)
        ->and(array_column($all, 'kind'))->toBe(['status', 'token', 'tool', 'error', 'terminal']);

    $afterTwo = $store->since($turn, 2);
    expect($afterTwo)->toHaveCount(3)
        ->and($afterTwo[0]['kind'])->toBe('tool')
        ->and($afterTwo[0]['index'])->toBe(2);
});

it('supports event kinds status token tool error terminal', function () {
    $store = new ArrayProgressStore;
    foreach (['status', 'token', 'tool', 'error', 'terminal'] as $kind) {
        $store->append('t1', ['kind' => $kind]);
    }
    expect(array_column($store->since('t1'), 'kind'))->toBe([
        'status', 'token', 'tool', 'error', 'terminal',
    ]);
});

it('RedisProgressStore is optional and works with a fake redis client', function () {
    $fake = new class
    {
        /** @var array<string, list<string>> */
        public array $lists = [];

        public function rPush(string $key, string $value): int
        {
            $this->lists[$key][] = $value;

            return count($this->lists[$key]);
        }

        /** @return list<string> */
        public function lRange(string $key, int $start, int $end): array
        {
            return $this->lists[$key] ?? [];
        }
    };

    $store = new RedisProgressStore($fake);
    expect($store)->toBeInstanceOf(ProgressStore::class);
    $store->append('turn-a', ['kind' => 'status', 'data' => ['s' => 1]]);
    $store->append('turn-a', ['kind' => 'terminal']);
    $events = $store->since('turn-a', 1);
    expect($events)->toHaveCount(1)->and($events[0]['kind'])->toBe('terminal');
});

it('RedisProgressStore accepts Laravel-style connection wrappers that only expose rpush via __call', function () {
    $native = new class
    {
        /** @var array<string, list<string>> */
        public array $lists = [];

        public function rPush(string $key, string $value): int
        {
            $this->lists[$key][] = $value;

            return count($this->lists[$key]);
        }

        /** @return list<string> */
        public function lRange(string $key, int $start, int $end): array
        {
            return $this->lists[$key] ?? [];
        }
    };

    // Mirrors Illuminate\Redis\Connections\Connection: no real rPush method, only __call.
    $wrapper = new class($native)
    {
        public function __construct(private object $client) {}

        public function client(): object
        {
            return $this->client;
        }

        public function __call(string $method, array $arguments): mixed
        {
            return $this->client->{$method}(...$arguments);
        }
    };

    $store = new RedisProgressStore($wrapper);
    $store->append('turn-wrap', ['kind' => 'status', 'data' => ['ok' => true]]);
    $store->append('turn-wrap', ['kind' => 'terminal']);

    $events = $store->since('turn-wrap', 0);
    expect($events)->toHaveCount(2)
        ->and($events[0]['kind'])->toBe('status')
        ->and($events[1]['kind'])->toBe('terminal');
});
