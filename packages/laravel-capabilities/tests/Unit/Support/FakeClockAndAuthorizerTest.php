<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\Authorizer;
use Rawphp\Capabilities\Contracts\Clock;
use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\Capabilities\Support\CapabilityFixture;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\Capabilities\Support\StubAuthorizer;

it('FixedClock implements Clock and returns frozen time', function () {
    $now = new DateTimeImmutable('2026-07-01T08:30:00+00:00');
    $clock = new FixedClock($now);

    expect($clock)->toBeInstanceOf(Clock::class)
        ->and($clock->now()->format(DateTimeInterface::ATOM))->toBe('2026-07-01T08:30:00+00:00');

    $clock->advance(new DateInterval('PT5M'));

    expect($clock->now()->format(DateTimeInterface::ATOM))->toBe('2026-07-01T08:35:00+00:00');
});

it('FixedClock requires a DateTimeImmutable and fails loudly without it', function () {
    expect(fn () => new FixedClock)
        ->toThrow(ArgumentCountError::class);
});

it('StubAuthorizer implements Authorizer with allow and deny modes', function () {
    $allow = StubAuthorizer::allow();
    $deny = StubAuthorizer::deny();

    expect($allow)->toBeInstanceOf(Authorizer::class)
        ->and($deny)->toBeInstanceOf(Authorizer::class)
        ->and($allow->authorize('create-invoice', [], null))->toBeTrue()
        ->and($deny->authorize('create-invoice', [], null))->toBeFalse();
});

it('InMemoryRateLimiter implements RateLimiter without external services', function () {
    $limiter = new InMemoryRateLimiter;

    expect($limiter)->toBeInstanceOf(RateLimiter::class)
        ->and($limiter->tooManyAttempts('actor:cap', 2))->toBeFalse();

    $limiter->hit('actor:cap', 60);
    $limiter->hit('actor:cap', 60);

    expect($limiter->tooManyAttempts('actor:cap', 2))->toBeTrue()
        ->and($limiter->remaining('actor:cap', 2))->toBe(0);
});

it('CapabilityFixture builds a pure in-memory capability definition', function () {
    $definition = CapabilityFixture::definition(
        name: 'test.ping',
        description: 'Ping fixture',
        mutating: false,
    );

    expect($definition['name'])->toBe('test.ping')
        ->and($definition['description'])->toBe('Ping fixture')
        ->and($definition['mutating'])->toBeFalse()
        ->and($definition['surfaces'])->toContain('http')
        ->and($definition)->toHaveKeys(['name', 'description', 'mutating', 'surfaces', 'input_schema', 'output_schema']);
});
