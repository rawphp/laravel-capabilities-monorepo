<?php

declare(strict_types=1);

use Rawphp\Capabilities\Schema\JsonSchemaValidator;

// Server is law (D-004): every string format the CLI rejects locally
// (capabilities-cli internal/run/validate.go formatViolation) must also be
// rejected here, and every value the CLI accepts must pass here too.

function formatSchema(string $format): array
{
    return [
        'type' => 'object',
        'properties' => ['value' => ['type' => 'string', 'format' => $format]],
    ];
}

$rejected = [
    ['date', '2026-02-30'],
    ['date', "2026-01-15\n"],
    ['date-time', '2026-01-15'],
    ['date-time', '2026-01-15 10:00:00Z'],
    ['date-time', '2026-01-15T10:00:00'],
    ['date-time', "2026-01-15T10:00:00Z\n"],
    ['datetime', 'yesterday'],
    ['time', '10:00'],
    ['time', '10:00:00Z'],
    ['email', 'not-an-email'],
    ['email', 'a b@example.com'],
    ['email', 'a@localhost'],
    ['uri', 'example.com'],
    ['url', 'www.example.com/x'],
    ['uuid', '123e4567-e89b-12d3-a456-42661417400'],
    ['uuid', 'not-a-uuid'],
    ['UUID', 'g23e4567-e89b-12d3-a456-426614174000'],
    [' Date-Time ', 'nope'],
];

$accepted = [
    ['date', '2024-02-29'],
    ['date-time', '2026-01-15T10:00:00Z'],
    ['date-time', '2026-01-15T10:00:00.123+10:00'],
    ['datetime', '2026-01-15T10:00:00-05:00'],
    ['time', '10:00:00'],
    ['time', '10:00:00.5'],
    ['email', 'ops@example.com'],
    ['uri', 'https://example.com/x'],
    ['uri', '/relative/path'],
    ['url', 'mailto://ops@example.com'],
    ['uuid', '123e4567-e89b-12d3-a456-426614174000'],
    ['uuid', 'ABCDEF01-E89B-12D3-A456-426614174000'],
    ['UUID', 'ffffffff-ffff-ffff-ffff-ffffffffffff'],
    ['hostname', 'anything goes'],
];

foreach ($rejected as [$format, $value]) {
    it('fail: portable format '.json_encode($format).' rejects '.json_encode($value).' like the CLI [D-004]', function () use ($format, $value) {
        $violations = (new JsonSchemaValidator)->validate(formatSchema($format), ['value' => $value]);

        expect($violations)->toHaveCount(1)
            ->and($violations[0]['field'])->toBe('value')
            ->and($violations[0]['message'])->toStartWith('invalid ')
            ->and($violations[0]['message'])->toEndWith(' format');
    });
}

foreach ($accepted as [$format, $value]) {
    it('happy: portable format '.json_encode($format).' accepts '.json_encode($value).' like the CLI [D-004]', function () use ($format, $value) {
        expect((new JsonSchemaValidator)->validate(formatSchema($format), ['value' => $value]))->toBeEmpty();
    });
}

it('happy: format violation messages name the format [D-004]', function () {
    $v = new JsonSchemaValidator;

    expect($v->validate(formatSchema('date'), ['value' => 'x'])[0]['message'])->toBe('invalid date format')
        ->and($v->validate(formatSchema('date-time'), ['value' => 'x'])[0]['message'])->toBe('invalid date-time format')
        ->and($v->validate(formatSchema('time'), ['value' => 'x'])[0]['message'])->toBe('invalid time format')
        ->and($v->validate(formatSchema('email'), ['value' => 'x'])[0]['message'])->toBe('invalid email format')
        ->and($v->validate(formatSchema('uri'), ['value' => 'x'])[0]['message'])->toBe('invalid uri format')
        ->and($v->validate(formatSchema('uuid'), ['value' => 'x'])[0]['message'])->toBe('invalid uuid format');
});

it('happy: format is ignored for non-string values [D-004]', function () {
    $schema = ['type' => 'object', 'properties' => ['value' => ['format' => 'uuid']]];

    expect((new JsonSchemaValidator)->validate($schema, ['value' => 42]))->toBeEmpty();
});
