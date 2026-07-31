<?php

// ORI-170: Server-side (domain, verb) collision on register/boot. Unit-only.

declare(strict_types=1);

use Rawphp\Capabilities\Boot\BootException;
use Rawphp\Capabilities\Boot\BootGuard;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;

it('fail: duplicate cli domain verb pair fails register with clear error [CLI-002]', function () {
    $registry = DiscoveryHelpers::registry();

    Capability::define('create-invoice')
        ->description('Create invoice')
        ->surfaces(['cli', 'http'])
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->cli('invoices', 'create')
        ->run(fn ($in) => new CreateInvoiceResult(invoice_id: 1))
        ->register($registry);

    expect(fn () => Capability::define('create-invoice-v2')
        ->description('Also create invoice')
        ->surfaces(['cli', 'http'])
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->cli('invoices', 'create')
        ->run(fn ($in) => new CreateInvoiceResult(invoice_id: 2))
        ->register($registry)
    )->toThrow(InvalidArgumentException::class);

    try {
        Capability::define('create-invoice-v3')
            ->description('Third collision')
            ->surfaces(['cli', 'http'])
            ->input(CreateInvoiceInput::class)
            ->output(CreateInvoiceResult::class)
            ->cli('invoices', 'create')
            ->run(fn ($in) => new CreateInvoiceResult(invoice_id: 3))
            ->register($registry);
        expect(false)->toBeTrue('expected register collision');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())
            ->toContain('invoices')
            ->toContain('create')
            ->and(
                str_contains($e->getMessage(), 'create-invoice')
                || str_contains($e->getMessage(), 'create-invoice-v3')
            )->toBeTrue();
    }
});

it('happy: different cli pairs register without collision [CLI-002]', function () {
    $registry = DiscoveryHelpers::registry();

    Capability::define('create-invoice')
        ->description('Create')
        ->surfaces(['cli', 'http'])
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->cli('invoices', 'create')
        ->run(fn ($in) => new CreateInvoiceResult(invoice_id: 1))
        ->register($registry);

    Capability::define('void-invoice')
        ->description('Void')
        ->surfaces(['cli', 'http'])
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->cli('invoices', 'void')
        ->run(fn ($in) => new CreateInvoiceResult(invoice_id: 1))
        ->register($registry);

    Capability::define('list-customers')
        ->description('List')
        ->readOnly(true)
        ->surfaces(['cli', 'http'])
        ->cli('customers', 'list')
        ->register($registry);

    expect($registry->has('create-invoice'))->toBeTrue()
        ->and($registry->has('void-invoice'))->toBeTrue()
        ->and($registry->has('list-customers'))->toBeTrue();
});

it('happy: unmapped capabilities do not collide on missing cli [CLI-002]', function () {
    $registry = DiscoveryHelpers::registry();

    Capability::define('cap-a')
        ->description('A')
        ->readOnly(true)
        ->surfaces(['http'])
        ->register($registry);

    Capability::define('cap-b')
        ->description('B')
        ->readOnly(true)
        ->surfaces(['http'])
        ->register($registry);

    expect($registry->has('cap-a'))->toBeTrue()
        ->and($registry->has('cap-b'))->toBeTrue();
});

it('fail: BootGuard rejects duplicate cli pairs across definitions [CLI-002]', function () {
    $definitions = [
        new CapabilityDefinition(
            name: 'create-invoice',
            description: 'Create',
            readOnly: true,
            cliDomain: 'invoices',
            cliVerb: 'create',
        ),
        new CapabilityDefinition(
            name: 'create-invoice-alias',
            description: 'Also create',
            readOnly: true,
            cliDomain: 'invoices',
            cliVerb: 'create',
        ),
    ];

    expect(fn () => BootGuard::assertUniqueCliPairs($definitions))
        ->toThrow(BootException::class);

    try {
        BootGuard::assertUniqueCliPairs($definitions);
        expect(false)->toBeTrue('expected boot collision');
    } catch (BootException $e) {
        expect($e->getMessage())
            ->toContain('invoices')
            ->toContain('create');
    }
});

it('happy: BootGuard accepts unique and unmapped cli pairs [CLI-002]', function () {
    $definitions = [
        new CapabilityDefinition(
            name: 'create-invoice',
            description: 'Create',
            readOnly: true,
            cliDomain: 'invoices',
            cliVerb: 'create',
        ),
        new CapabilityDefinition(
            name: 'void-invoice',
            description: 'Void',
            readOnly: true,
            cliDomain: 'invoices',
            cliVerb: 'void',
        ),
        new CapabilityDefinition(
            name: 'unmapped',
            description: 'No cli',
            readOnly: true,
        ),
    ];

    BootGuard::assertUniqueCliPairs($definitions);

    expect(true)->toBeTrue();
});
