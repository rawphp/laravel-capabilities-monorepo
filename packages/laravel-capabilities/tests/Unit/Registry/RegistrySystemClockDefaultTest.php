<?php

// REQ-034: Registry production clock default is SystemClock (UR-005).

declare(strict_types=1);

use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\SystemClock;

it('default construction uses SystemClock not a frozen FixedClock', function () {
    $registry = new CapabilityRegistry;

    expect($registry->clock())->toBeInstanceOf(SystemClock::class)
        ->and($registry->clock())->not->toBeInstanceOf(FixedClock::class);
});

it('makeRegistry production path uses SystemClock', function () {
    $registry = ContainerBindings::makeRegistry(CapabilitiesConfig::defaults(), new ArrayTableGateway);

    expect($registry->clock())->toBeInstanceOf(SystemClock::class)
        ->and($registry->clock())->not->toBeInstanceOf(FixedClock::class);
});

it('constructor accepts an explicit FixedClock for deterministic tests', function () {
    $fixed = new FixedClock(new DateTimeImmutable('2026-01-15T12:00:00Z'));
    $registry = new CapabilityRegistry(clock: $fixed);

    expect($registry->clock())->toBe($fixed)
        ->and($registry->clock())->toBeInstanceOf(FixedClock::class)
        ->and($registry->clock()->now()->format(DateTimeInterface::ATOM))
        ->toBe('2026-01-15T12:00:00+00:00');
});

it('withClock freezes time after default SystemClock construction', function () {
    $registry = new CapabilityRegistry;
    expect($registry->clock())->toBeInstanceOf(SystemClock::class);

    $fixed = new FixedClock(new DateTimeImmutable('2026-03-01T00:00:00Z'));
    $registry->withClock($fixed);

    expect($registry->clock())->toBe($fixed)
        ->and($registry->clock())->toBeInstanceOf(FixedClock::class);
});
