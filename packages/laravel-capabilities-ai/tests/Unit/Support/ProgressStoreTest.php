<?php

declare(strict_types=1);

use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\ContainerBindings;
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
            return array_slice($this->lists[$key] ?? [], $start);
        }

        public function expire(string $key, int $seconds): bool
        {
            return true;
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
            return array_slice($this->lists[$key] ?? [], $start);
        }

        public function expire(string $key, int $seconds): bool
        {
            return true;
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

it('RedisProgressStore gives concurrent appends for one turn distinct indexes', function () {
    $redis = new class
    {
        /** @var array<string, list<string>> */
        public array $lists = [];

        /** @var (callable(): void)|null */
        public $beforeFirstPush = null;

        public function rPush(string $key, string $value): int
        {
            if ($this->beforeFirstPush !== null) {
                $interleave = $this->beforeFirstPush;
                $this->beforeFirstPush = null;
                $interleave();
            }
            $this->lists[$key][] = $value;

            return count($this->lists[$key]);
        }

        /** @return list<string> */
        public function lRange(string $key, int $start, int $end): array
        {
            return array_slice($this->lists[$key] ?? [], $start);
        }

        public function expire(string $key, int $seconds): bool
        {
            return true;
        }
    };

    $liveWorker = new RedisProgressStore($redis);
    $retriedJob = new RedisProgressStore($redis);

    // The retried job appends between the live worker's read and its push.
    $redis->beforeFirstPush = fn () => $retriedJob->append('turn-race', ['kind' => 'error']);
    $liveWorker->append('turn-race', ['kind' => 'status']);

    $events = $liveWorker->since('turn-race', 0);
    expect(array_column($events, 'index'))->toBe([0, 1])
        ->and(array_column($events, 'kind'))->toBe(['error', 'status'])
        ->and($liveWorker->since('turn-race', 1))->toHaveCount(1);
});

it('RedisProgressStore rejects events without a kind and clients without rpush', function () {
    $store = new RedisProgressStore(new class {});

    expect(fn () => $store->append('turn-x', ['kind' => '']))
        ->toThrow(InvalidArgumentException::class, 'Progress event requires kind')
        ->and(fn () => $store->append('turn-x', ['kind' => 'status']))
        ->toThrow(RuntimeException::class, 'Redis client missing rPush');
});

/**
 * Redis fake with real LRANGE start/stop semantics and an EXPIRE log.
 */
function ttlRedisFake(): object
{
    return new class
    {
        /** @var array<string, list<string>> */
        public array $lists = [];

        /** @var list<array{0: string, 1: int}> */
        public array $expires = [];

        /** @var list<array{0: int, 1: int}> */
        public array $ranges = [];

        public function rPush(string $key, string $value): int
        {
            $this->lists[$key][] = $value;

            return count($this->lists[$key]);
        }

        public function expire(string $key, int $seconds): bool
        {
            $this->expires[] = [$key, $seconds];

            return true;
        }

        /** @return list<string> */
        public function lRange(string $key, int $start, int $end): array
        {
            $this->ranges[] = [$start, $end];
            $list = $this->lists[$key] ?? [];

            return array_slice($list, $start, $end === -1 ? null : $end - $start + 1);
        }
    };
}

it('RedisProgressStore expires each turn key after append (transient progress)', function () {
    $redis = ttlRedisFake();
    $store = new RedisProgressStore($redis, 'p:', ttlSeconds: 3600);

    $store->append('turn-ttl', ['kind' => 'status']);
    $store->append('turn-ttl', ['kind' => 'terminal']);

    expect($redis->expires)->toBe([['p:turn-ttl', 3600], ['p:turn-ttl', 3600]]);
});

it('RedisProgressStore defaults the key TTL to one day', function () {
    $redis = ttlRedisFake();
    (new RedisProgressStore($redis))->append('turn-d', ['kind' => 'status']);

    expect($redis->expires)->toBe([['capabilities_ai:progress:turn-d', RedisProgressStore::DEFAULT_TTL_SECONDS]])
        ->and(RedisProgressStore::DEFAULT_TTL_SECONDS)->toBe(86400);
});

it('RedisProgressStore since() reads from the cursor instead of the whole list', function () {
    $redis = ttlRedisFake();
    $store = new RedisProgressStore($redis);
    foreach (['a', 'b', 'c', 'd'] as $kind) {
        $store->append('turn-c', ['kind' => $kind]);
    }

    $events = $store->since('turn-c', 2);

    expect($redis->ranges)->toBe([[2, -1]])
        ->and(array_column($events, 'kind'))->toBe(['c', 'd'])
        ->and(array_column($events, 'index'))->toBe([2, 3])
        ->and(array_column($store->since('turn-c', -5), 'index'))->toBe([0, 1, 2, 3]);
});

it('RedisProgressStore rejects a non-positive TTL and clients without expire', function () {
    expect(fn () => new RedisProgressStore(ttlRedisFake(), ttlSeconds: 0))
        ->toThrow(InvalidArgumentException::class, 'ttl');

    $noExpire = new class
    {
        public function rPush(string $key, string $value): int
        {
            return 1;
        }
    };

    expect(fn () => (new RedisProgressStore($noExpire))->append('t', ['kind' => 'status']))
        ->toThrow(RuntimeException::class, 'Redis client missing expire');
});

it('makeProgressStore passes progress.ttl_seconds to the redis store', function () {
    $redis = ttlRedisFake();
    $store = ContainerBindings::makeProgressStore(
        ['progress' => ['driver' => 'redis', 'redis_key_prefix' => 't:', 'ttl_seconds' => 60]],
        $redis,
    );
    $store->append('turn-cfg', ['kind' => 'status']);

    expect($redis->expires)->toBe([['t:turn-cfg', 60]]);
});
