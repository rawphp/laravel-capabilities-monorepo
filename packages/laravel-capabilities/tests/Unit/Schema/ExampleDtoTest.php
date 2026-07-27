<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;

it('happy: example CreateInvoiceInput field customer_id in schema [D-015]', function () {
    $schema = CreateInvoiceInput::jsonSchema();
    expect($schema['properties'])->toHaveKey('customer_id')
        ->and($schema['properties']['customer_id']['type'])->toBe('integer');
});

it('happy: example CreateInvoiceInput field amount_cents in schema [D-015]', function () {
    $schema = CreateInvoiceInput::jsonSchema();
    expect($schema['properties'])->toHaveKey('amount_cents')
        ->and($schema['properties']['amount_cents']['type'])->toBe('integer');
});

it('happy: example CreateInvoiceInput field currency in schema [D-015]', function () {
    $schema = CreateInvoiceInput::jsonSchema();
    expect($schema['properties'])->toHaveKey('currency')
        ->and($schema['properties']['currency']['type'])->toBe('string');
});

it('happy: example CreateInvoiceInput field memo in schema [D-015]', function () {
    $schema = CreateInvoiceInput::jsonSchema();
    expect($schema['properties'])->toHaveKey('memo');
});

it('happy: example output invoice_id in schema [D-015]', function () {
    $schema = CreateInvoiceResult::jsonSchema();
    expect($schema['properties'])->toHaveKey('invoice_id')
        ->and($schema['required'] ?? [])->toContain('invoice_id');
});

it('fail: example server exists rule not in portable schema [D-004]', function () {
    $rules = CreateInvoiceInput::rules();
    $schema = json_encode(CreateInvoiceInput::jsonSchema());
    expect($rules['customer_id'])->toContain('exists:customers,id')
        ->and($schema)->not->toContain('exists:customers,id');
});
