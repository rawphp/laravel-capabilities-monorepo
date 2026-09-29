<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Rawphp\Capabilities\Boot\BootException;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\Contracts\RateLimitCache;
use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\Capabilities\Support\ArrayRateLimitCache;
use Rawphp\Capabilities\Support\IlluminateRateLimitCache;
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\Capabilities\Support\LaravelCacheRateLimiter;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;
use Rawphp\Capabilities\Tests\Fixtures\FakeProviderApp;

it('LaravelCacheRateLimiter implements RateLimiter over injectable cache store', function () {
    $cache = new ArrayRateLimitCache;
    $limiter = new LaravelCacheRateLimiter($cache);

    expect($limiter)->toBeInstanceOf(RateLimiter::class)
        ->and($limiter->tooManyAttempts('actor:cap', 2))->toBeFalse();
});

it('LaravelCacheRateLimiter allows hits under the max then denies after limit', function () {
    $cache = new ArrayRateLimitCache;
    $limiter = new LaravelCacheRateLimiter($cache);

    expect($limiter->tooManyAttempts('k1', 2))->toBeFalse()
        ->and($limiter->remaining('k1', 2))->toBe(2);

    expect($limiter->hit('k1', 60))->toBe(1)
        ->and($limiter->tooManyAttempts('k1', 2))->toBeFalse()
        ->and($limiter->remaining('k1', 2))->toBe(1);

    expect($limiter->hit('k1', 60))->toBe(2)
        ->and($limiter->tooManyAttempts('k1', 2))->toBeTrue()
        ->and($limiter->remaining('k1', 2))->toBe(0);

    // Further hits still count but stay over limit.
    $limiter->hit('k1', 60);
    expect($limiter->tooManyAttempts('k1', 2))->toBeTrue();
});

it('LaravelCacheRateLimiter clear resets attempts so traffic is allowed again', function () {
    $cache = new ArrayRateLimitCache;
    $limiter = new LaravelCacheRateLimiter($cache);

    $limiter->hit('reset-me', 60);
    $limiter->hit('reset-me', 60);
    expect($limiter->tooManyAttempts('reset-me', 2))->toBeTrue();

    $limiter->clear('reset-me');

    expect($limiter->tooManyAttempts('reset-me', 2))->toBeFalse()
        ->and($limiter->remaining('reset-me', 2))->toBe(2);
});

it('LaravelCacheRateLimiter keys are isolated across actors', function () {
    $cache = new ArrayRateLimitCache;
    $limiter = new LaravelCacheRateLimiter($cache);

    $limiter->hit('a', 60);
    $limiter->hit('a', 60);

    expect($limiter->tooManyAttempts('a', 2))->toBeTrue()
        ->and($limiter->tooManyAttempts('b', 2))->toBeFalse();
});

it('makeRateLimiter returns InMemoryRateLimiter for driver memory', function () {
    $config = BootHelpers::config([
        'rate_limits' => ['driver' => 'memory'],
    ]);

    $limiter = ContainerBindings::makeRateLimiter($config);

    expect($limiter)->toBeInstanceOf(InMemoryRateLimiter::class)
        ->and($limiter)->toBeInstanceOf(RateLimiter::class);
});

it('makeRateLimiter returns LaravelCacheRateLimiter for driver cache with store', function () {
    $config = BootHelpers::config([
        'rate_limits' => ['driver' => 'cache'],
    ]);
    $cache = new ArrayRateLimitCache;

    $limiter = ContainerBindings::makeRateLimiter($config, $cache);

    expect($limiter)->toBeInstanceOf(LaravelCacheRateLimiter::class);
});

it('makeRateLimiter fails closed when driver is cache and no cache store is provided', function () {
    $config = BootHelpers::config([
        'rate_limits' => ['driver' => 'cache'],
    ]);

    expect(fn () => ContainerBindings::makeRateLimiter($config))
        ->toThrow(BootException::class);
});

it('makeRegistry wires rate limiter from rate_limits.driver memory', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
        'rate_limits' => ['driver' => 'memory', 'enabled' => true],
    ]);

    $registry = ContainerBindings::makeRegistry($config);

    expect($registry->rateLimiter())->toBeInstanceOf(InMemoryRateLimiter::class);
});

it('makeRegistry wires LaravelCacheRateLimiter when driver is cache', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
        'rate_limits' => ['driver' => 'cache', 'enabled' => true],
    ]);
    $cache = new ArrayRateLimitCache;

    $registry = ContainerBindings::makeRegistry(
        $config,
        null,
        null,
        null,
        null,
        $cache,
    );

    expect($registry->rateLimiter())->toBeInstanceOf(LaravelCacheRateLimiter::class);
});

it('resolve reports rate_limits driver concrete for memory and cache', function () {
    $memory = ContainerBindings::resolve(BootHelpers::config([
        'rate_limits' => ['driver' => 'memory'],
    ]));
    $cache = ContainerBindings::resolve(BootHelpers::config([
        'rate_limits' => ['driver' => 'cache'],
    ]));

    expect($memory['drivers']['rate_limits']['resolved'])->toBe('memory')
        ->and($memory['drivers']['rate_limits']['concrete'])->toBe(InMemoryRateLimiter::class)
        ->and($cache['drivers']['rate_limits']['resolved'])->toBe('cache')
        ->and($cache['drivers']['rate_limits']['concrete'])->toBe(LaravelCacheRateLimiter::class);
});

it('package default rate_limits.driver is cache for multi-worker production', function () {
    $defaults = CapabilitiesConfig::defaults();

    expect($defaults['rate_limits'])->toHaveKey('driver')
        ->and($defaults['rate_limits']['driver'])->toBe('cache');
});

function illuminateRateCache(): array
{
    $repository = new Repository(new ArrayStore);

    return [$repository, new IlluminateRateLimitCache($repository)];
}

it('over an Illuminate cache repository the window denies at the limit and reopens after decay [L-008]', function () {
    Carbon::setTestNow('2026-09-30 10:00:00');
    try {
        [$repository, $cache] = illuminateRateCache();
        $limiter = new LaravelCacheRateLimiter($cache);

        $limiter->hit('actor:cap', 60);
        $limiter->hit('actor:cap', 60);

        expect($limiter->tooManyAttempts('actor:cap', 2))->toBeTrue()
            ->and($repository->get('capabilities:rate:actor:cap'))->toBe(2)
            ->and($limiter->availableIn('actor:cap'))->toBeGreaterThan(0);

        Carbon::setTestNow('2026-09-30 10:01:01');

        expect($limiter->tooManyAttempts('actor:cap', 2))->toBeFalse()
            ->and($limiter->remaining('actor:cap', 2))->toBe(2);

        $limiter->clear('actor:cap');
        expect($repository->has('capabilities:rate:actor:cap:timer'))->toBeFalse();
    } finally {
        Carbon::setTestNow();
    }
});

it('drops a counter left over the limit without a live window timer instead of denying forever', function () {
    [$repository, $cache] = illuminateRateCache();
    $repository->forever('capabilities:rate:stale', 5);
    $limiter = new LaravelCacheRateLimiter($cache);

    expect($limiter->tooManyAttempts('stale', 2))->toBeFalse()
        ->and($repository->has('capabilities:rate:stale'))->toBeFalse()
        ->and($limiter->remaining('stale', 2))->toBe(2);
});

it('re-seeds a zero counter that has no TTL so the window is bounded', function () {
    Carbon::setTestNow('2026-09-30 10:00:00');
    try {
        [$repository, $cache] = illuminateRateCache();
        $repository->forever('capabilities:rate:race', 0);
        $limiter = new LaravelCacheRateLimiter($cache);

        expect($limiter->hit('race', 60))->toBe(1)
            ->and($repository->get('capabilities:rate:race'))->toBe(1);

        Carbon::setTestNow('2026-09-30 10:01:01');

        expect($repository->has('capabilities:rate:race'))->toBeFalse();
    } finally {
        Carbon::setTestNow();
    }
});

/**
 * @param  array<string, mixed>  $instances
 */
function rateLimiterFromProvider(array $instances): RateLimiter
{
    $app = FakeProviderApp::registered(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
        'audit' => ['driver' => 'memory'],
        'rate_limits' => ['driver' => 'cache'],
    ]), $instances);

    return $app->make(RateLimiter::class);
}

it('provider: rate_limits.driver=cache counts in a host-bound RateLimitCache first', function () {
    $bound = new ArrayRateLimitCache;
    [$repository] = illuminateRateCache();

    rateLimiterFromProvider([RateLimitCache::class => $bound, 'cache.store' => $repository])->hit('k', 60);

    expect($bound->get('capabilities:rate:k'))->toBe(1)
        ->and($repository->has('capabilities:rate:k'))->toBeFalse();
});

it('provider: rate_limits.driver=cache falls back to cache.store, then the cache Repository binding', function () {
    [$store] = illuminateRateCache();
    [$contract] = illuminateRateCache();

    rateLimiterFromProvider(['cache.store' => $store])->hit('via-store', 60);
    rateLimiterFromProvider([CacheRepository::class => $contract])->hit('via-contract', 60);
    rateLimiterFromProvider(['cache.store' => new stdClass, CacheRepository::class => $contract])->hit('store-not-a-repository', 60);

    expect($store->get('capabilities:rate:via-store'))->toBe(1)
        ->and($contract->get('capabilities:rate:via-contract'))->toBe(1)
        ->and($contract->get('capabilities:rate:store-not-a-repository'))->toBe(1);
});

it('provider: a host RateLimitCache binding of the wrong type fails boot rather than falling back', function () {
    [$repository] = illuminateRateCache();

    rateLimiterFromProvider([RateLimitCache::class => new stdClass, 'cache.store' => $repository]);
})->throws(BootException::class, 'rate_limits.driver=cache requires a RateLimitCache');

it('provider: rate_limits.driver=cache fails boot when no cache binding resolves', function () {
    rateLimiterFromProvider([]);
})->throws(BootException::class, 'rate_limits.driver=cache requires a RateLimitCache');
