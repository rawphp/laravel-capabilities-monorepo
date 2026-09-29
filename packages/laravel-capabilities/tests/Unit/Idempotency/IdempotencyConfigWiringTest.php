<?php

// L-011 / D-005: the published idempotency config (enabled, ttl_hours, header, warn_missing_key)
// reaches the guard the pipeline actually uses, and the HTTP header name comes from the same key.

declare(strict_types=1);

use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Tests\Support\SharedFakes;

function idemWiringRegistry(array $idempotencyConfig, ?FixedClock $clock = null): array
{
    $clock ??= new FixedClock(new DateTimeImmutable('2026-03-01T00:00:00+00:00'));
    $fakes = SharedFakes::create(clock: $clock);
    $runs = 0;
    $registry = new CapabilityRegistry(
        authorizer: $fakes->authorizer,
        approvalStore: $fakes->approvals,
        idempotencyStore: $fakes->idempotency,
        auditWriter: $fakes->audit,
        rateLimiter: $fakes->rateLimiter,
        clock: $clock,
        idempotencyConfig: $idempotencyConfig,
    );
    Capability::define('idem-wire')
        ->description('idempotency wiring')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(function () use (&$runs) {
            $runs++;

            return new CreateInvoiceResult(invoice_id: $runs);
        })
        ->register($registry);

    return ['registry' => $registry, 'store' => $fakes->idempotency, 'clock' => $clock, 'runs' => &$runs];
}

it('happy: constructor idempotency config drives the stored row TTL [L-011]', function () {
    $h = idemWiringRegistry(['ttl_hours' => 1]);

    $h['registry']->invoke('idem-wire', PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'idem-ttl-1']));
    $row = $h['store']->find('t-1', 'user', '7', 'idem-wire', 'idem-ttl-1');

    expect($row['expires_at'])->toBe('2026-03-01T01:00:00+00:00')
        ->and($h['registry']->idempotencyConfig()->ttlHours)->toBe(1);
});

it('happy: withIdempotencyConfig rebuilds the guard and keeps the store; withIdempotencyStore keeps the config [L-011]', function () {
    $h = idemWiringRegistry([]);
    $store = $h['store'];

    $h['registry']->withIdempotencyConfig(['ttl_hours' => 2, 'header' => 'X-Idem', 'warn_missing_key' => false]);
    expect($h['registry']->idempotencyConfig()->ttlHours)->toBe(2)
        ->and($h['registry']->idempotencyConfig()->header)->toBe('X-Idem')
        ->and($h['registry']->idempotencyStore())->toBe($store);

    $other = new InMemoryIdempotencyStore($h['clock']);
    $h['registry']->withIdempotencyStore($other);
    expect($h['registry']->idempotencyStore())->toBe($other)
        ->and($h['registry']->idempotencyConfig()->ttlHours)->toBe(2);

    $h['registry']->invoke('idem-wire', PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'idem-ttl-2']));
    expect($other->find('t-1', 'user', '7', 'idem-wire', 'idem-ttl-2')['expires_at'])->toBe('2026-03-01T02:00:00+00:00');
});

it('edge: warn_missing_key=false silences the missing-key warning [L-011]', function () {
    $quiet = idemWiringRegistry(['warn_missing_key' => false]);
    $loud = idemWiringRegistry([]);

    $quiet['registry']->invoke('idem-wire', PipelineHelpers::validInput(), PipelineHelpers::options());
    $loud['registry']->invoke('idem-wire', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($quiet['registry']->idempotencyWarnings())->toBe([])
        ->and($loud['registry']->idempotencyWarnings())->toHaveCount(1);
});

it('edge: enabled=false turns the guard off — nothing stored, repeats run again [L-011]', function () {
    $h = idemWiringRegistry(['enabled' => false]);
    $options = PipelineHelpers::options('http', ['idempotency_key' => 'idem-off-1']);

    $first = $h['registry']->invoke('idem-wire', PipelineHelpers::validInput(), $options);
    $second = $h['registry']->invoke('idem-wire', PipelineHelpers::validInput(), $options);

    expect($first->data->invoice_id)->toBe(1)
        ->and($second->data->invoice_id)->toBe(2)
        ->and($second->meta['idempotent_replay'])->toBeFalse()
        ->and($h['store']->find('t-1', 'user', '7', 'idem-wire', 'idem-off-1'))->toBeNull();
});

it('happy: makeRegistry applies config.idempotency to the guard [L-011]', function () {
    $registry = ContainerBindings::makeRegistry(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory', 'ttl_hours' => 3, 'header' => 'X-Idem', 'warn_missing_key' => false],
    ]));

    expect($registry->idempotencyConfig()->ttlHours)->toBe(3)
        ->and($registry->idempotencyConfig()->header)->toBe('X-Idem')
        ->and($registry->idempotencyConfig()->warnMissingKey)->toBeFalse();
});
