<?php

// Registry gates (unknown / sunset / surface) are observable like other pipeline denies. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Events\CapabilityFailed;
use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;

it('fail: surface-disabled invoke writes a deny audit entry and emits CapabilityFailed [PIPE-005]', function () {
    $h = CatalogHelpers::harness(['cap_surfaces' => ['http']]);

    $r = $h['registry']->invoke($h['name'], CatalogHelpers::input(), CatalogHelpers::options('mcp'));

    $audit = $h['fakes']->audit->all();
    $failed = $h['registry']->failedEvents();
    expect($r->errorCode())->toBe('forbidden')
        ->and($audit)->toHaveCount(1)
        ->and($audit[0]['event'])->toBe('capability.failed')
        ->and($audit[0]['capability_name'])->toBe('create-invoice')
        ->and($audit[0]['caller'])->toBe('mcp')
        ->and($audit[0]['request_id'])->toBe('req-catalog-1')
        ->and($audit[0]['result'])->toMatchArray(['ok' => false, 'code' => 'forbidden'])
        ->and($failed)->toHaveCount(1)
        ->and($failed[0])->toBeInstanceOf(CapabilityFailed::class)
        ->and($failed[0]->code)->toBe('forbidden')
        ->and($failed[0]->caller)->toBe('mcp')
        ->and($r->meta['request_id'] ?? null)->toBe('req-catalog-1');
});

it('fail: past-sunset invoke writes a deny audit entry and emits CapabilityFailed, keeping successor [D-012]', function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'sunset_at' => '2020-01-01',
        'successor' => 'create-invoice-v2',
        'aliases' => ['invoice.create'],
    ]);

    $r = $h['registry']->invoke('invoice.create', CatalogHelpers::input(), CatalogHelpers::options('cli'));

    $audit = $h['fakes']->audit->all();
    $failed = $h['registry']->failedEvents();
    expect($r->errorCode())->toBe('gone')
        ->and($r->error['http_status'] ?? null)->toBe(410)
        ->and($r->error['successor'] ?? null)->toBe('create-invoice-v2')
        ->and($audit)->toHaveCount(1)
        ->and($audit[0]['capability_name'])->toBe('create-invoice')
        ->and($audit[0]['result'])->toMatchArray(['ok' => false, 'code' => 'gone'])
        ->and($failed)->toHaveCount(1)
        ->and($failed[0]->capability)->toBe('create-invoice')
        ->and($failed[0]->code)->toBe('gone');
});

it('fail: unknown capability emits CapabilityFailed and an error log under the requested name without audit [PIPE-001]', function () {
    $h = CatalogHelpers::harness();

    $r = $h['registry']->invoke('drop-all-tables', [], CatalogHelpers::options('agent'));

    $failed = $h['registry']->failedEvents();
    $logs = $h['registry']->logs();
    expect($r->errorCode())->toBe('not_found')
        ->and($h['fakes']->audit->all())->toBe([])
        ->and($failed)->toHaveCount(1)
        ->and($failed[0]->capability)->toBe('drop-all-tables')
        ->and($failed[0]->code)->toBe('not_found')
        ->and($failed[0]->caller)->toBe('agent')
        ->and(end($logs))->toMatchArray([
            'level' => 'error',
            'context' => ['capability' => 'drop-all-tables', 'code' => 'not_found', 'caller' => 'agent'],
        ]);
});

it('edge: gate denies still never call run() [PIPE-005]', function () {
    $runs = new stdClass;
    $runs->value = 0;
    $h = CatalogHelpers::harness([
        'cap_surfaces' => ['http'],
        'run' => function () use ($runs) {
            $runs->value++;

            return new CreateInvoiceResult(invoice_id: 1);
        },
    ]);

    $h['registry']->invoke($h['name'], CatalogHelpers::input(), CatalogHelpers::options('agent'));

    expect($runs->value)->toBe(0);
});
