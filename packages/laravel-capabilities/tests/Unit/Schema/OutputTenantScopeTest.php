<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Schema\OutputValidator;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;
use Rawphp\Capabilities\Tests\Fixtures\TenantScopedListResult;
use Rawphp\Capabilities\Tests\Fixtures\TenantScopedResult;

function tenantScopedDefinition(string $output): CapabilityDefinition
{
    return Capability::define('scoped-out-'.md5($output))
        ->input(CreateInvoiceInput::class)
        ->output($output)
        ->run(fn () => null)
        ->toDefinition();
}

function tenantContext(?string $tenantId): CapabilityContext
{
    return new CapabilityContext(
        caller: 'http',
        actor: SystemActor::named('test'),
        scope: new CapabilityScope(tenantId: $tenantId),
    );
}

it('happy: Field tenantScoped marks the property x-tenant-scoped in JSON Schema [D-003][D-014]', function () {
    $schema = TenantScopedResult::jsonSchema();

    expect($schema['properties']['tenant_id']['x-tenant-scoped'])->toBeTrue()
        ->and($schema['properties']['invoice_id'])->not->toHaveKey('x-tenant-scoped');
});

it('happy: scoped output field matching the invoking tenant passes [D-003][D-014]', function () {
    $check = (new OutputValidator)->validate(
        tenantScopedDefinition(TenantScopedResult::class),
        new TenantScopedResult(invoice_id: 1, tenant_id: 'tenant-a'),
        tenantContext('tenant-a'),
    );

    expect($check)->toBeNull();
});

it('fail: scoped output field from another tenant fails closed with output_invalid [D-003][D-014]', function () {
    $check = (new OutputValidator)->validate(
        tenantScopedDefinition(TenantScopedResult::class),
        ['invoice_id' => 1, 'tenant_id' => 'tenant-b'],
        tenantContext('tenant-a'),
    );

    expect($check)->not->toBeNull()
        ->and($check->errorCode())->toBe('output_invalid')
        ->and($check->toArray()['error']['violations'])->toBe([
            ['field' => 'tenant_id', 'message' => 'does not belong to the active tenant'],
        ]);
});

it('fail: cross-tenant item nested in an array output fails closed [D-003][D-014]', function () {
    $check = (new OutputValidator)->validate(
        tenantScopedDefinition(TenantScopedListResult::class),
        ['invoices' => [
            ['invoice_id' => 1, 'tenant_id' => 'tenant-a'],
            ['invoice_id' => 2, 'tenant_id' => 'tenant-b'],
        ]],
        tenantContext('tenant-a'),
    );

    expect($check?->errorCode())->toBe('output_invalid')
        ->and($check->toArray()['error']['violations'][0]['field'])->toBe('invoices.1.tenant_id');
});

it('edge: null scoped value, null tenant (global system), or no context skip the tenant check [D-003][D-014]', function () {
    $validator = new OutputValidator;
    $definition = tenantScopedDefinition(TenantScopedResult::class);

    expect($validator->validate($definition, ['invoice_id' => 1, 'tenant_id' => null], tenantContext('tenant-a')))->toBeNull()
        ->and($validator->validate($definition, ['invoice_id' => 1, 'tenant_id' => 'tenant-b'], tenantContext(null)))->toBeNull()
        ->and($validator->validate($definition, ['invoice_id' => 1, 'tenant_id' => 'tenant-b']))->toBeNull();
});

it('fail: invoke returning another tenant\'s resource maps to output_invalid via the pipeline [D-003][D-014]', function () {
    $registry = DiscoveryHelpers::registry();
    Capability::define('scoped-leak')
        ->input(CreateInvoiceInput::class)
        ->output(TenantScopedResult::class)
        ->run(fn () => new TenantScopedResult(invoice_id: 9, tenant_id: 'tenant-b'))
        ->register($registry);

    $input = ['customer_id' => 1, 'amount_cents' => 100, 'currency' => 'USD'];
    $leak = $registry->invoke('scoped-leak', $input, ['scope' => new CapabilityScope(tenantId: 'tenant-a')]);
    $own = $registry->invoke('scoped-leak', $input, ['scope' => new CapabilityScope(tenantId: 'tenant-b')]);

    expect($leak->errorCode())->toBe('output_invalid')
        ->and($own->isOk())->toBeTrue();
});
