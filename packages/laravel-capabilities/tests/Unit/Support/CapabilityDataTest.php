<?php

declare(strict_types=1);

use Rawphp\Capabilities\Attributes\Field;
use Rawphp\Capabilities\Support\CapabilityData;

/**
 * Concrete DTO used only by Support unit tests.
 */
final class CreateInvoiceInputStub extends CapabilityData
{
    public function __construct(
        #[Field(description: 'Customer id within the active tenant')]
        public int $customer_id,
        public int $amount_cents,
        public string $currency,
        public ?string $memo = null,
    ) {}

    public static function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'memo' => ['nullable', 'string', 'max:500'],
        ];
    }
}

it('happy: fromArray hydrates typed DTO [D-015]', function () {
    $dto = CreateInvoiceInputStub::fromArray([
        'customer_id' => 7,
        'amount_cents' => 2500,
        'currency' => 'USD',
        'memo' => 'hello',
    ]);

    expect($dto)->toBeInstanceOf(CreateInvoiceInputStub::class)
        ->and($dto)->toBeInstanceOf(CapabilityData::class)
        ->and($dto->customer_id)->toBe(7)
        ->and($dto->amount_cents)->toBe(2500)
        ->and($dto->currency)->toBe('USD')
        ->and($dto->memo)->toBe('hello');
});

it('happy: toArray round trips public props [D-015]', function () {
    $dto = CreateInvoiceInputStub::fromArray([
        'customer_id' => 1,
        'amount_cents' => 100,
        'currency' => 'EUR',
    ]);

    $array = $dto->toArray();

    expect($array)->toBe([
        'customer_id' => 1,
        'amount_cents' => 100,
        'currency' => 'EUR',
        'memo' => null,
    ]);

    $again = CreateInvoiceInputStub::fromArray($array);

    expect($again->toArray())->toBe($array);
});

it('fail: fromArray rejects unknown keys when additionalProperties false [D-015]', function () {
    expect(fn () => CreateInvoiceInputStub::fromArray([
        'customer_id' => 1,
        'amount_cents' => 100,
        'currency' => 'USD',
        'evil_extra' => true,
    ]))->toThrow(InvalidArgumentException::class);
});

it('happy: jsonSchema static generation [D-015]', function () {
    $schema = CreateInvoiceInputStub::jsonSchema();

    expect($schema)->toBeArray()
        ->and($schema['type'] ?? null)->toBe('object')
        ->and($schema['additionalProperties'] ?? null)->toBeFalse()
        ->and($schema['required'] ?? [])->toContain('customer_id')
        ->and($schema['required'] ?? [])->toContain('amount_cents')
        ->and($schema['required'] ?? [])->toContain('currency')
        ->and($schema['required'] ?? [])->not->toContain('memo')
        ->and($schema['properties']['customer_id']['type'] ?? null)->toBe('integer')
        ->and($schema['properties']['customer_id']['description'] ?? null)
        ->toBe('Customer id within the active tenant')
        ->and($schema['properties']['memo']['type'] ?? null)->toBe(['string', 'null']);
});

it('edge: rules server-only separate from jsonSchema [D-004]', function () {
    $rules = CreateInvoiceInputStub::rules();
    $schema = CreateInvoiceInputStub::jsonSchema();
    $encoded = json_encode($schema);

    expect($rules['customer_id'] ?? [])->toContain('exists:customers,id')
        ->and($encoded)->not->toContain('exists:customers,id')
        ->and($encoded)->not->toContain('exists:');
});

it('fail: incomplete fromArray missing required field fails closed [D-015]', function () {
    expect(fn () => CreateInvoiceInputStub::fromArray([
        'customer_id' => 1,
        // amount_cents missing
        'currency' => 'USD',
    ]))->toThrow(InvalidArgumentException::class);
});
