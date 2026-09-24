<?php

declare(strict_types=1);

use Rawphp\Capabilities\Observability\InMemoryMetrics;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\RedisProgressStore;
use Rawphp\CapabilitiesAi\Support\StoreBoundProgressStoreReadiness;

function progressStoreThatThrows(): ProgressStore
{
    return new class implements ProgressStore
    {
        public int $appends = 0;

        public function append(string $turnUlid, array $event): void
        {
            $this->appends++;
        }

        public function since(string $turnUlid, int $cursor = 0): array
        {
            throw new RuntimeException('connection refused');
        }
    };
}

it('is ready when the store since() ping succeeds and records no metric', function () {
    $metrics = new InMemoryMetrics;
    $readiness = new StoreBoundProgressStoreReadiness(new ArrayProgressStore, $metrics);

    expect($readiness->isReady())->toBeTrue()
        ->and($metrics->get(StoreBoundProgressStoreReadiness::METRIC_NOT_READY, [
            'store' => ArrayProgressStore::class,
        ]))->toBe(0);
});

it('is not ready when the store ping throws and records the not-ready metric', function () {
    $store = progressStoreThatThrows();
    $metrics = new InMemoryMetrics;
    $readiness = new StoreBoundProgressStoreReadiness($store, $metrics);

    expect($readiness->isReady())->toBeFalse()
        ->and($readiness->isReady())->toBeFalse()
        ->and($metrics->get(StoreBoundProgressStoreReadiness::METRIC_NOT_READY, [
            'store' => $store::class,
        ]))->toBe(2);
});

it('is not ready without metrics bound', function () {
    expect((new StoreBoundProgressStoreReadiness(progressStoreThatThrows()))->isReady())->toBeFalse();
});

it('pings read-only: never appends a progress event', function () {
    $store = new ArrayProgressStore;
    (new StoreBoundProgressStoreReadiness($store))->isReady();

    expect($store->since(StoreBoundProgressStoreReadiness::PING_TURN))->toBe([]);

    $spy = progressStoreThatThrows();
    (new StoreBoundProgressStoreReadiness($spy))->isReady();

    expect($spy->appends)->toBe(0);
});

it('reports RedisProgressStore not ready when the Redis client read fails', function () {
    $redis = new class
    {
        public function lRange(string $key, int $start, int $end): array
        {
            throw new RuntimeException('READONLY / connection lost');
        }
    };

    $metrics = new InMemoryMetrics;
    $readiness = new StoreBoundProgressStoreReadiness(new RedisProgressStore($redis), $metrics);

    expect($readiness->isReady())->toBeFalse()
        ->and($metrics->get(StoreBoundProgressStoreReadiness::METRIC_NOT_READY, [
            'store' => RedisProgressStore::class,
        ]))->toBe(1);
});

it('reports RedisProgressStore ready when the Redis client answers the ping key', function () {
    $redis = new class
    {
        /** @var list<string> */
        public array $keys = [];

        public function lRange(string $key, int $start, int $end): array
        {
            $this->keys[] = $key;

            return [];
        }
    };

    $readiness = new StoreBoundProgressStoreReadiness(new RedisProgressStore($redis, 'p:'));

    expect($readiness->isReady())->toBeTrue()
        ->and($redis->keys)->toBe(['p:'.StoreBoundProgressStoreReadiness::PING_TURN]);
});
