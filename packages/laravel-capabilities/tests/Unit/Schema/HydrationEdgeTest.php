<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\LineItemDto;
use Rawphp\Capabilities\Tests\Fixtures\NestedInput;

it('edge: hydration case extra_keys_rejected_when_additionalProperties_false [D-015]', function () {
    expect(fn () => CreateInvoiceInput::fromArray([
        'customer_id' => 1,
        'amount_cents' => 1,
        'currency' => 'USD',
        'nope' => true,
    ]))->toThrow(InvalidArgumentException::class);
});

it('edge: hydration case null_for_nullable_ok [D-015]', function () {
    $dto = CreateInvoiceInput::fromArray([
        'customer_id' => 1,
        'amount_cents' => 1,
        'currency' => 'USD',
        'memo' => null,
    ]);
    expect($dto->memo)->toBeNull();
});

it('edge: hydration case null_for_required_rejected [D-015]', function () {
    expect(fn () => CreateInvoiceInput::fromArray([
        'customer_id' => null,
        'amount_cents' => 1,
        'currency' => 'USD',
    ]))->toThrow(InvalidArgumentException::class);
});

it('edge: hydration case string_int_coercion_policy [D-015]', function () {
    // Numeric strings coerce to int for UX at wire edge.
    $dto = CreateInvoiceInput::fromArray([
        'customer_id' => '12',
        'amount_cents' => '100',
        'currency' => 'USD',
    ]);
    expect($dto->customer_id)->toBe(12)
        ->and($dto->amount_cents)->toBe(100);
});

it('edge: hydration case empty_array_ok_when_allowed [D-015]', function () {
    $dto = NestedInput::fromArray([
        'item' => ['sku' => 'A', 'qty' => 1],
        'items' => [],
    ]);
    expect($dto->items)->toBe([]);
});

it('edge: hydration case nested_object_hydrated [D-015]', function () {
    $dto = NestedInput::fromArray([
        'item' => ['sku' => 'SKU-1', 'qty' => 2],
        'items' => [],
    ]);
    expect($dto->item)->toBeInstanceOf(LineItemDto::class)
        ->and($dto->item->sku)->toBe('SKU-1')
        ->and($dto->item->qty)->toBe(2);
});

it('edge: hydration case list_of_objects_hydrated [D-015]', function () {
    $dto = NestedInput::fromArray([
        'item' => ['sku' => 'A', 'qty' => 1],
        'items' => [
            ['sku' => 'B', 'qty' => 3],
            ['sku' => 'C', 'qty' => 4],
        ],
    ]);
    expect($dto->items)->toHaveCount(2)
        ->and($dto->items[0])->toBeInstanceOf(LineItemDto::class)
        ->and($dto->items[1]->sku)->toBe('C');
});
