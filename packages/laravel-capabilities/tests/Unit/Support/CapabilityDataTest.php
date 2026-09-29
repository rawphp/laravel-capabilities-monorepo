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

final class SensitiveFieldInputStub extends CapabilityData
{
    public function __construct(
        #[Field(description: 'Tax file number', sensitive: true)]
        public string $tfn,
        public string $name,
    ) {}
}

it('happy: a sensitive field is marked writeOnly in the portable schema [D-010]', function () {
    $schema = SensitiveFieldInputStub::jsonSchema();

    expect($schema['properties']['tfn'])->toBe(['type' => 'string', 'description' => 'Tax file number', 'writeOnly' => true])
        ->and($schema['properties']['name'])->toBe(['type' => 'string']);
});

final class NullOnlyUnionDto extends CapabilityData
{
    public function __construct(
        public ?int $maybe = null,
        public NestedCovDto|string|null $mixed = null,
    ) {}
}

final class NestedCovDto extends CapabilityData
{
    public function __construct(public string $label) {}
}

final class UntypedParamDto extends CapabilityData
{
    public function __construct(
        public $value,
        public ?int $optionalNull = null,
    ) {}
}

it('edge: a nullable union hydrates null, a nested DTO array and a plain string', function () {
    $a = NullOnlyUnionDto::fromArray(['maybe' => null, 'mixed' => null]);
    expect($a->maybe)->toBeNull();

    $b = NullOnlyUnionDto::fromArray(['maybe' => 3, 'mixed' => ['label' => 'x']]);
    expect($b->mixed)->toBeInstanceOf(NestedCovDto::class);

    $c = NullOnlyUnionDto::fromArray(['maybe' => 1, 'mixed' => 'plain']);
    expect($c->mixed)->toBe('plain');
});

it('happy: a nullable union property appears in the portable schema', function () {
    $schema = NullOnlyUnionDto::jsonSchema();

    expect($schema['properties'])->toHaveKey('mixed');
});

it('edge: an untyped constructor parameter passes through and an omitted optional nullable defaults to null', function () {
    $u = UntypedParamDto::fromArray(['value' => ['nested' => true]]);
    expect($u->value)->toBe(['nested' => true])
        ->and($u->optionalNull)->toBeNull();

    // optional null via omit
    $u2 = UntypedParamDto::fromArray(['value' => 1]);
    expect($u2->optionalNull)->toBeNull();
});

final class EmptyCtorDto extends CapabilityData
{
    // no constructor
}

final class NestedAddressDto extends CapabilityData
{
    public function __construct(
        public string $city,
    ) {}
}

final class UnionAndNestedDto extends CapabilityData
{
    public function __construct(
        public int|string $id,
        public ?NestedAddressDto $address = null,
        #[Field(items: NestedAddressDto::class, minItems: 0, maxItems: 5)]
        public array $locations = [],
        #[Field(description: 'amount', minimum: 0, maximum: 9999, format: 'int32')]
        public float $amount = 0.0,
        #[Field(enum: ['a', 'b'], minLength: 1, maxLength: 1)]
        public string $code = 'a',
        public bool $flag = false,
        public ?string $note = null,
        public $untyped = null,
    ) {}
}

final class StaticPropDto extends CapabilityData
{
    public static string $ignored = 'x';

    public function __construct(
        public string $name,
    ) {}
}

it('CapabilityData handles no-ctor DTOs, unions, nested DTOs, field constraints, and coercion errors', function () {
    expect(EmptyCtorDto::fromArray([]))->toBeInstanceOf(EmptyCtorDto::class);
    expect(fn () => EmptyCtorDto::fromArray(['x' => 1]))->toThrow(InvalidArgumentException::class);
    expect(EmptyCtorDto::fromArray(['x' => 1], allowAdditionalProperties: true))->toBeInstanceOf(EmptyCtorDto::class);

    $dto = UnionAndNestedDto::fromArray([
        'id' => '42',
        'address' => ['city' => 'Berlin'],
        'locations' => [['city' => 'NYC']],
        'amount' => '1.5',
        'code' => 'b',
        'flag' => true,
        'note' => null,
    ]);
    expect($dto->id)->toBe('42')
        ->and($dto->address)->toBeInstanceOf(NestedAddressDto::class)
        ->and($dto->locations[0])->toBeInstanceOf(NestedAddressDto::class)
        ->and($dto->amount)->toBe(1.5)
        ->and($dto->toArray()['address']['city'])->toBe('Berlin');

    $schema = UnionAndNestedDto::jsonSchema();
    expect($schema['properties']['amount']['minimum'] ?? null)->toBe(0)
        ->and($schema['properties']['code']['enum'] ?? null)->toBe(['a', 'b'])
        ->and($schema['properties']['code']['minLength'] ?? null)->toBe(1)
        ->and($schema['properties']['locations']['items'] ?? null)->not->toBeNull();

    expect(UnionAndNestedDto::from(['id' => 7])->id)->toBeIn([7, '7']);
    expect(UnionAndNestedDto::validate(['id' => 1]))->toBeInstanceOf(UnionAndNestedDto::class);
    expect(UnionAndNestedDto::rules())->toBe([]);

    expect(fn () => UnionAndNestedDto::fromArray([]))->toThrow(InvalidArgumentException::class); // missing id
    expect(fn () => UnionAndNestedDto::fromArray(['id' => null]))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnionAndNestedDto::fromArray(['id' => 1, 'flag' => 'yes']))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnionAndNestedDto::fromArray(['id' => 1, 'amount' => 'nope']))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnionAndNestedDto::fromArray(['id' => 1, 'address' => 'x']))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnionAndNestedDto::fromArray(['id' => 1, 'locations' => 'x']))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnionAndNestedDto::fromArray(['id' => 1, 'locations' => ['not-object']]))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnionAndNestedDto::fromArray(['id' => 1, 'code' => false]))->toThrow(InvalidArgumentException::class);

    $s = StaticPropDto::fromArray(['name' => 'n']);
    expect($s->toArray())->toBe(['name' => 'n']); // static prop skipped
});

final class NeedDefaultDto extends CapabilityData
{
    public function __construct(
        public string $name = 'default',
        public ?int $n = null,
    ) {}
}

final class StrictUnionDto extends CapabilityData
{
    public function __construct(public int|bool $flag) {}
}

it('CapabilityData fromArray falls back to constructor defaults for missing keys', function () {
    expect(NeedDefaultDto::fromArray([])->name)->toBe('default');
});

it('CapabilityData rejects a value matching no union member and still exposes the union in jsonSchema', function () {
    expect(fn () => StrictUnionDto::fromArray(['flag' => 'nope']))->toThrow(InvalidArgumentException::class);
    expect(StrictUnionDto::fromArray(['flag' => true])->flag)->toBeTrue();
    expect(StrictUnionDto::jsonSchema()['properties']['flag'] ?? null)->not->toBeNull();
});
