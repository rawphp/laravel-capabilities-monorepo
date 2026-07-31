<?php

// REQ-070 / L-009: Idempotency default driver aligns with durable approvals (database).

declare(strict_types=1);

use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore;

it('REQ-070: package defaults set idempotency.driver to database like approval.store', function () {
    expect(CapabilitiesConfig::get('idempotency.driver'))->toBe('database')
        ->and(CapabilitiesConfig::get('approval.store'))->toBe('database');
});

it('REQ-070: resolve defaults idempotency driver to database when config uses package defaults', function () {
    $resolved = ContainerBindings::resolve(CapabilitiesConfig::defaults());

    expect($resolved['drivers']['idempotency']['resolved'])->toBe('database')
        ->and($resolved['drivers']['idempotency']['concrete'])->toBe(DatabaseIdempotencyStore::class)
        ->and($resolved['drivers']['approval_store']['resolved'])->toBe('database');
});

it('REQ-070: resolve falls back to database when idempotency.driver key is missing', function () {
    $config = CapabilitiesConfig::defaults();
    unset($config['idempotency']['driver']);

    $resolved = ContainerBindings::resolve($config);

    expect($resolved['drivers']['idempotency']['resolved'])->toBe('database');
});
