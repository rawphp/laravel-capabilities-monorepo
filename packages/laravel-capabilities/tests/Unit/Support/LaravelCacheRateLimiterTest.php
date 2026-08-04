<?php

declare(strict_types=1);

use Rawphp\Capabilities\Boot\BootException;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\Capabilities\Support\ArrayRateLimitCache;
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\Capabilities\Support\LaravelCacheRateLimiter;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

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
