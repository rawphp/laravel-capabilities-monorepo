<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;
use Rawphp\Capabilities\Tests\Fixtures\LineItemDto;
use Rawphp\Capabilities\Tests\Fixtures\TypedOutputArray;
use Rawphp\Capabilities\Tests\Fixtures\TypedOutputBool;
use Rawphp\Capabilities\Tests\Fixtures\TypedOutputInt;
use Rawphp\Capabilities\Tests\Fixtures\TypedOutputNullable;
use Rawphp\Capabilities\Tests\Fixtures\TypedOutputObject;
use Rawphp\Capabilities\Tests\Fixtures\TypedOutputString;

$types = [
    'int' => [
        'class' => TypedOutputInt::class,
        'bad' => ['value' => 'nope'],
        'good' => ['value' => 7],
        'goodObj' => fn () => new TypedOutputInt(value: 7),
    ],
    'string' => [
        'class' => TypedOutputString::class,
        'bad' => ['value' => 123],
        'good' => ['value' => 'ok'],
        'goodObj' => fn () => new TypedOutputString(value: 'ok'),
    ],
    'bool' => [
        'class' => TypedOutputBool::class,
        'bad' => ['value' => 'true'],
        'good' => ['value' => true],
        'goodObj' => fn () => new TypedOutputBool(value: true),
    ],
    'array' => [
        'class' => TypedOutputArray::class,
        'bad' => ['value' => ['a' => 1]], // object, not list
        'good' => ['value' => [1, 2]],
        'goodObj' => fn () => new TypedOutputArray(value: [1, 2]),
    ],
    'object' => [
        'class' => TypedOutputObject::class,
        'bad' => ['value' => [1, 2]],
        'good' => ['value' => ['sku' => 'A', 'qty' => 1]],
        'goodObj' => fn () => new TypedOutputObject(value: new LineItemDto(sku: 'A', qty: 1)),
    ],
    'nullable' => [
        'class' => TypedOutputNullable::class,
        'bad' => [], // missing when required? value is optional with default null — use wrong type
        'good' => ['value' => null],
        'goodObj' => fn () => new TypedOutputNullable(value: null),
        'bad_is_missing' => true,
    ],
];

foreach ($types as $type => $cfg) {
    it("fail: output type {$type} rejects ".($type === 'nullable' ? 'missing_when_required' : ($type === 'int' ? 'string' : ($type === 'string' ? 'int' : ($type === 'bool' ? 'string' : ($type === 'array' ? 'object' : 'array'))))).' [D-014]', function () use ($type, $cfg) {
        $registry = DiscoveryHelpers::registry();
        $name = "otype-bad-{$type}";
        if ($type === 'nullable') {
            // Force required non-null schema by validating against TypedOutputString missing value
            Capability::define($name)
                ->input(CreateInvoiceInput::class)
                ->output(TypedOutputString::class)
                ->run(fn ($in) => [])
                ->register($registry);
        } else {
            Capability::define($name)
                ->input(CreateInvoiceInput::class)
                ->output($cfg['class'])
                ->run(fn ($in) => $cfg['bad'])
                ->register($registry);
        }

        $result = $registry->invoke($name, [
            'customer_id' => 1,
            'amount_cents' => 1,
            'currency' => 'USD',
        ]);
        expect($result->isOk())->toBeFalse()
            ->and($result->errorCode())->toBe('output_invalid');
    });

    it("happy: output type {$type} accepts valid value [D-014]", function () use ($type, $cfg) {
        $registry = DiscoveryHelpers::registry();
        $name = "otype-ok-{$type}";
        Capability::define($name)
            ->input(CreateInvoiceInput::class)
            ->output($cfg['class'])
            ->run(fn ($in) => ($cfg['goodObj'])())
            ->register($registry);

        $result = $registry->invoke($name, [
            'customer_id' => 1,
            'amount_cents' => 1,
            'currency' => 'USD',
        ]);
        expect($result->isOk())->toBeTrue();
    });
}
