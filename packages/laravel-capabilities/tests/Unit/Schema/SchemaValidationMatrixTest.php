<?php

declare(strict_types=1);

use Rawphp\Capabilities\Schema\JsonSchemaValidator;

function portableSchema(string $kind): array
{
    return match ($kind) {
        'integer' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value'],
            'properties' => ['value' => ['type' => 'integer']],
        ],
        'string' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value'],
            'properties' => ['value' => ['type' => 'string']],
        ],
        'boolean' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value'],
            'properties' => ['value' => ['type' => 'boolean']],
        ],
        'array' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value'],
            'properties' => ['value' => ['type' => 'array', 'items' => ['type' => 'string']]],
        ],
        'object' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value'],
            'properties' => [
                'value' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => ['x' => ['type' => 'integer']],
                ],
            ],
        ],
        'enum' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value'],
            'properties' => ['value' => ['type' => 'string', 'enum' => ['a', 'b']]],
        ],
        'format_date' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value'],
            'properties' => ['value' => ['type' => 'string', 'format' => 'date']],
        ],
        'minLength' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value'],
            'properties' => ['value' => ['type' => 'string', 'minLength' => 3]],
        ],
        'maxLength' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value'],
            'properties' => ['value' => ['type' => 'string', 'maxLength' => 3]],
        ],
        'minimum' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value'],
            'properties' => ['value' => ['type' => 'integer', 'minimum' => 5]],
        ],
        'maximum' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value'],
            'properties' => ['value' => ['type' => 'integer', 'maximum' => 5]],
        ],
        'required' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value'],
            'properties' => ['value' => ['type' => 'string']],
        ],
        'additionalProperties_false' => [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => ['value' => ['type' => 'string']],
        ],
        default => throw new InvalidArgumentException($kind),
    };
}

$cases = [
    ['integer', 'string', ['value' => 'x'], ['value' => 1]],
    ['integer', 'null_when_required', ['value' => null], ['value' => 2]],
    ['string', 'integer', ['value' => 1], ['value' => 'ok']],
    ['boolean', 'string', ['value' => 'true'], ['value' => true]],
    ['array', 'object', ['value' => ['x' => 1]], ['value' => ['a', 'b']]],
    ['object', 'array', ['value' => [1, 2]], ['value' => ['x' => 1]]],
    ['enum', 'outside_set', ['value' => 'z'], ['value' => 'a']],
    ['format_date', 'invalid_date', ['value' => 'not-a-date'], ['value' => '2026-01-15']],
    ['minLength', 'too_short', ['value' => 'ab'], ['value' => 'abc']],
    ['maxLength', 'too_long', ['value' => 'abcd'], ['value' => 'ab']],
    ['minimum', 'below_min', ['value' => 1], ['value' => 5]],
    ['maximum', 'above_max', ['value' => 9], ['value' => 5]],
    ['required', 'missing', [], ['value' => 'present']],
    ['additionalProperties_false', 'unknown_key', ['value' => 'ok', 'extra' => 1], ['value' => 'ok']],
];

// Integer has two fail/happy pairs in inventory (string + null_when_required + two happy).
// Extra happy for integer is covered as second integer happy case below.

foreach ($cases as [$kind, $badLabel, $bad, $good]) {
    it("fail: portable validation rejects {$kind} when {$badLabel} [D-004]", function () use ($kind, $bad) {
        $v = new JsonSchemaValidator;
        $violations = $v->validate(portableSchema($kind), $bad);
        expect($violations)->not->toBeEmpty();
    });

    it("happy: portable validation accepts valid {$kind}".($kind === 'integer' && $badLabel === 'null_when_required' ? ' (case 1)' : '').' [D-004]', function () use ($kind, $good) {
        $v = new JsonSchemaValidator;
        $violations = $v->validate(portableSchema($kind), $good);
        expect($violations)->toBeEmpty();
    });
}
