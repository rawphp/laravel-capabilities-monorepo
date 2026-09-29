<?php

declare(strict_types=1);

use Rawphp\CapabilitiesAi\Support\ToolSchemaHash;

it('hashes a tool definition by its parameters schema only', function () {
    $schema = ['type' => 'object', 'properties' => ['a' => ['type' => 'integer']]];

    expect(ToolSchemaHash::of(['name' => 'x.y', 'parameters' => $schema]))
        ->toBe(ToolSchemaHash::of(['name' => 'x.y', 'description' => 'changed copy', 'parameters' => $schema]))
        ->and(ToolSchemaHash::of(['name' => 'x.y', 'parameters' => $schema]))->toMatch('/^[0-9a-f]{64}$/');
});

it('ignores object key order but not schema content', function () {
    $a = ['type' => 'object', 'properties' => ['a' => ['type' => 'integer', 'minimum' => 1]], 'required' => ['a']];
    $reordered = ['required' => ['a'], 'properties' => ['a' => ['minimum' => 1, 'type' => 'integer']], 'type' => 'object'];
    $changed = ['type' => 'object', 'properties' => ['a' => ['type' => 'string', 'minimum' => 1]], 'required' => ['a']];

    expect(ToolSchemaHash::of(['parameters' => $a]))->toBe(ToolSchemaHash::of(['parameters' => $reordered]))
        ->and(ToolSchemaHash::of(['parameters' => $a]))->not->toBe(ToolSchemaHash::of(['parameters' => $changed]));
});

it('treats a tool without parameters as an empty schema', function () {
    expect(ToolSchemaHash::of(['name' => 'x.y']))->toBe(ToolSchemaHash::of(['name' => 'x.y', 'parameters' => []]));
});
