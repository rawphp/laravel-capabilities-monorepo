<?php

// Opt-in idempotency keys derived from declared input fields (D-005). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Attributes\Capability as CapabilityAttribute;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Contracts\DefinesCapability;
use Rawphp\Capabilities\Discovery\AttributeDiscoverer;
use Rawphp\Capabilities\Idempotency\IdempotencyKey;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;

#[CapabilityAttribute(
    name: 'create-invoice-derived',
    input: CreateInvoiceInput::class,
    output: CreateInvoiceResult::class,
    idempotencyKeyFields: ['customer_id', 'currency'],
)]
final class DerivedKeyAttributedCapability implements DefinesCapability
{
    public function run(CreateInvoiceInput $input): CreateInvoiceResult
    {
        return new CreateInvoiceResult(invoice_id: 1);
    }
}

it('happy: derive hashes only the named fields into a valid key [D-005]', function () {
    $key = IdempotencyKey::derive(['customer_id', 'currency'], IdempotencyHelpers::inputA());

    expect($key)->toBeString()
        ->and(IdempotencyKey::isValid($key))->toBeTrue()
        ->and($key)->toStartWith('derived:')
        ->and(IdempotencyKey::derive(['currency', 'customer_id'], [
            'currency' => 'USD',
            'amount_cents' => 999,
            'customer_id' => 1,
        ]))->toBe($key)
        ->and(IdempotencyKey::derive(['customer_id', 'currency'], IdempotencyHelpers::inputB()))->not->toBe($key);
});

it('edge: derive returns null without fields or when a named field is missing or null [D-005]', function () {
    expect(IdempotencyKey::derive([], IdempotencyHelpers::inputA()))->toBeNull()
        ->and(IdempotencyKey::derive(['memo'], IdempotencyHelpers::inputA()))->toBeNull()
        ->and(IdempotencyKey::derive(['memo'], ['memo' => null]))->toBeNull();
});

it('happy: fluent builder and attribute carry idempotency key fields [D-005]', function () {
    $fluent = Capability::define('create-invoice')
        ->input(CreateInvoiceInput::class)
        ->idempotencyKeyFields(['customer_id', 'currency'])
        ->toDefinition();
    $attributed = (new AttributeDiscoverer)->fromClass(DerivedKeyAttributedCapability::class);

    expect($fluent->idempotencyKeyFields)->toBe(['customer_id', 'currency'])
        ->and($attributed?->idempotencyKeyFields)->toBe(['customer_id', 'currency'])
        ->and(IdempotencyHelpers::mutatingDefinition()->idempotencyKeyFields)->toBe([]);
});

it('fail: key fields rejected when the capability never stores keys or a name is blank [D-005]', function (array $args) {
    expect(fn () => new CapabilityDefinition(...$args))->toThrow(InvalidArgumentException::class);
})->with([
    'readOnly' => [['name' => 'list-invoices', 'readOnly' => true, 'idempotencyKeyFields' => ['id']]],
    'idempotent none' => [['name' => 'log-event', 'input' => CreateInvoiceInput::class, 'idempotent' => 'none', 'idempotencyKeyFields' => ['id']]],
    'blank name' => [['name' => 'create-invoice', 'input' => CreateInvoiceInput::class, 'idempotencyKeyFields' => ['']]],
]);

it('happy: same key fields without a supplied key replay instead of running twice [D-005]', function () {
    $h = IdempotencyHelpers::harness(['idempotencyKeyFields' => ['customer_id', 'currency']]);
    $a = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('mcp'));
    $b = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('mcp'));

    $derived = IdempotencyKey::derive(['customer_id', 'currency'], IdempotencyHelpers::inputA());
    expect($a->isOk())->toBeTrue()
        ->and($b->isReplay())->toBeTrue()
        ->and($b->data)->toEqual($a->data)
        ->and($h['runCount']->value)->toBe(1)
        ->and($h['store']->find('tenant-1', 'user', '7', $h['name'], (string) $derived))->not->toBeNull();
});

it('fail: same key fields with a different payload conflicts [D-005]', function () {
    $h = IdempotencyHelpers::harness(['idempotencyKeyFields' => ['customer_id', 'currency']]);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http'));
    $r = $h['registry']->invoke(
        $h['name'],
        array_merge(IdempotencyHelpers::inputA(), ['amount_cents' => 500]),
        IdempotencyHelpers::options('http'),
    );

    expect($r->errorCode())->toBe('conflict')
        ->and($h['runCount']->value)->toBe(1);
});

it('happy: different key field values run separately [D-005]', function () {
    $h = IdempotencyHelpers::harness(['idempotencyKeyFields' => ['customer_id', 'currency']]);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http'));
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputB(), IdempotencyHelpers::options('http'));

    expect($h['runCount']->value)->toBe(2);
});

it('happy: an explicit key wins over the derived key [D-005]', function () {
    $h = IdempotencyHelpers::harness(['idempotencyKeyFields' => ['customer_id', 'currency']]);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http', ['idempotency_key' => 'k-1']));
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http', ['idempotency_key' => 'k-2']));

    expect($h['runCount']->value)->toBe(2)
        ->and($h['store']->find('tenant-1', 'user', '7', $h['name'], 'k-1'))->not->toBeNull();
});

it('happy: derived key satisfies required idempotency [D-005]', function () {
    $h = IdempotencyHelpers::harness([
        'idempotent' => 'required',
        'idempotencyKeyFields' => ['customer_id', 'currency'],
    ]);
    $r = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http'));

    expect($r->isOk())->toBeTrue();
});

it('edge: missing key field falls back to the non-idempotent path [D-005]', function () {
    $h = IdempotencyHelpers::harness(['idempotencyKeyFields' => ['memo']]);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http'));
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http'));

    expect($h['runCount']->value)->toBe(2);
});
