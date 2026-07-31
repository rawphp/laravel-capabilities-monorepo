<?php

declare(strict_types=1);

use Rawphp\Capabilities\Attributes\Capability as CapabilityAttribute;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Discovery\AttributeDiscoverer;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Tests\Fixtures\Capabilities\AttributedCreateInvoice;
use Rawphp\Capabilities\Tests\Fixtures\Capabilities\AttributedCreateInvoiceWithCli;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;

it('happy: fluent builder stores cli domain and verb when declared [CLI-001]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = Capability::define('create-invoice')
        ->description('Create an invoice')
        ->surfaces(['cli'])
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->cli('invoices', 'create')
        ->run(fn ($in) => new CreateInvoiceResult(invoice_id: 1))
        ->register($registry);

    expect($def->cliDomain)->toBe('invoices')
        ->and($def->cliVerb)->toBe('create');
});

it('happy: attribute discovery stores cli domain and verb when declared [CLI-001]', function () {
    $def = (new AttributeDiscoverer)->fromClass(AttributedCreateInvoiceWithCli::class);

    expect($def)->not->toBeNull()
        ->and($def->cliDomain)->toBe('invoices')
        ->and($def->cliVerb)->toBe('create');
});

it('happy: omitted cli leaves domain and verb null [CLI-001]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'no-cli-cap');

    expect($def->cliDomain)->toBeNull()
        ->and($def->cliVerb)->toBeNull();
});

it('happy: attribute without cli leaves domain and verb null [CLI-001]', function () {
    $def = (new AttributeDiscoverer)->fromClass(AttributedCreateInvoice::class);

    expect($def)->not->toBeNull()
        ->and($def->cliDomain)->toBeNull()
        ->and($def->cliVerb)->toBeNull();
});

it('fail: incomplete cli with only domain rejected at definition time [CLI-001]', function () {
    expect(fn () => new CapabilityDefinition(
        name: 'partial-domain',
        readOnly: true,
        cliDomain: 'invoices',
        cliVerb: null,
    ))->toThrow(InvalidArgumentException::class);
});

it('fail: incomplete cli with only verb rejected at definition time [CLI-001]', function () {
    expect(fn () => new CapabilityDefinition(
        name: 'partial-verb',
        readOnly: true,
        cliDomain: null,
        cliVerb: 'create',
    ))->toThrow(InvalidArgumentException::class);
});

it('fail: invalid cli domain token rejected [CLI-001]', function () {
    expect(fn () => Capability::define('bad-domain')
        ->readOnly(true)
        ->cli('Invoices', 'create')
        ->toDefinition()
    )->toThrow(InvalidArgumentException::class);
});

it('fail: invalid cli verb token rejected [CLI-001]', function () {
    expect(fn () => Capability::define('bad-verb')
        ->readOnly(true)
        ->cli('invoices', 'create_invoice')
        ->toDefinition()
    )->toThrow(InvalidArgumentException::class);
});

it('fail: reserved meta domain rejected for cli domain [CLI-001]', function () {
    $reserved = ['auth', 'catalog', 'describe', 'run', 'mcp', 'approvals', 'version', 'help'];

    foreach ($reserved as $domain) {
        expect(fn () => Capability::define("cap-{$domain}")
            ->readOnly(true)
            ->cli($domain, 'list')
            ->toDefinition()
        )->toThrow(InvalidArgumentException::class, message: "reserved domain '{$domain}' should be rejected");
    }
});

it('fail: domain starting with digit rejected [CLI-001]', function () {
    expect(fn () => Capability::define('digit-domain')
        ->readOnly(true)
        ->cli('9invoices', 'create')
        ->toDefinition()
    )->toThrow(InvalidArgumentException::class);
});

it('fail: empty domain rejected [CLI-001]', function () {
    expect(fn () => Capability::define('empty-domain')
        ->readOnly(true)
        ->cli('', 'create')
        ->toDefinition()
    )->toThrow(InvalidArgumentException::class);
});

it('happy: valid kebab-case domain and verb accepted [CLI-001]', function () {
    $def = Capability::define('list-line-items')
        ->readOnly(true)
        ->cli('line-items', 'list-all')
        ->toDefinition();

    expect($def->cliDomain)->toBe('line-items')
        ->and($def->cliVerb)->toBe('list-all');
});

it('happy: attribute constructor accepts cliDomain and cliVerb [CLI-001]', function () {
    $attr = new CapabilityAttribute(
        name: 'create-invoice',
        cliDomain: 'invoices',
        cliVerb: 'create',
    );

    expect($attr->cliDomain)->toBe('invoices')
        ->and($attr->cliVerb)->toBe('create');
});
