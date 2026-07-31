<?php

// ORI-170: Catalog wire for optional cli domain/verb metadata. Unit-only.

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;

it('happy: catalog list emits nested cli when both domain and verb present [CLI-002]', function () {
    $registry = DiscoveryHelpers::registry();
    Capability::define('create-invoice')
        ->description('Create an invoice')
        ->surfaces(['agent', 'mcp', 'http', 'cli'])
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->cli('invoices', 'create')
        ->run(fn ($in) => new CreateInvoiceResult(invoice_id: 1))
        ->register($registry);

    $list = $registry->catalog()->list();

    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('cli')
        ->and($list[0]['cli'])->toBe([
            'domain' => 'invoices',
            'verb' => 'create',
        ]);
});

it('happy: catalog describe emits nested cli when both domain and verb present [CLI-002]', function () {
    $registry = DiscoveryHelpers::registry();
    Capability::define('create-invoice')
        ->description('Create an invoice')
        ->surfaces(['agent', 'mcp', 'http', 'cli'])
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->cli('invoices', 'create')
        ->run(fn ($in) => new CreateInvoiceResult(invoice_id: 1))
        ->register($registry);

    $desc = $registry->catalog()->describe('create-invoice');

    expect($desc)->toHaveKey('cli')
        ->and($desc['cli'])->toBe([
            'domain' => 'invoices',
            'verb' => 'create',
        ])
        ->and($desc)->toHaveKeys(['input_schema', 'output_schema']);
});

it('happy: catalog list omits cli when unmapped [CLI-002]', function () {
    $h = CatalogHelpers::harness();
    $list = $h['catalog']->list();

    expect($list)->not->toBeEmpty()
        ->and($list[0])->not->toHaveKey('cli')
        ->and($list[0])->toHaveKey('name');
});

it('happy: catalog describe omits cli when unmapped [CLI-002]', function () {
    $h = CatalogHelpers::harness();
    $desc = $h['catalog']->describe($h['name']);

    expect($desc)->not->toHaveKey('cli')
        ->and($desc)->toHaveKeys(['name', 'input_schema', 'output_schema']);
});

it('happy: omitted cli leaves catalog entry valid [CLI-002]', function () {
    $h = CatalogHelpers::harness(['name' => 'list-customers', 'description' => 'List customers']);
    $entry = $h['catalog']->list()[0];

    expect($entry)->toHaveKeys([
        'name',
        'description',
        'surfaces',
        'readOnly',
        'schema_version',
        'idempotent',
        'deprecated',
        'aliases',
        'groups',
        'tags',
    ])
        ->and($entry)->not->toHaveKey('cli')
        ->and($entry['name'])->toBe('list-customers');
});
