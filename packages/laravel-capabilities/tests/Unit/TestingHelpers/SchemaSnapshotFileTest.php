<?php

// D-020: durable schema snapshot files — malformed files fail loudly, bare schemas lock input only. Unit-only.

declare(strict_types=1);

use Rawphp\Capabilities\Support\SchemaSnapshot;
use Rawphp\Capabilities\Support\SchemaSnapshotException;

/**
 * Write $contents to a throwaway snapshot file, hand its path to $use, then remove it.
 */
function withSnapshotFile(string $contents, callable $use): mixed
{
    $path = tempnam(sys_get_temp_dir(), 'cap-snap-');
    file_put_contents($path, $contents);

    try {
        return $use($path);
    } finally {
        @unlink($path);
    }
}

it('fail: a snapshot file that is not valid JSON names the capability, path, and parse error [D-020]', function () {
    withSnapshotFile('{"input_schema": ', function (string $path): void {
        expect(fn () => SchemaSnapshot::loadFile('create-invoice', $path))
            ->toThrow(SchemaSnapshotException::class, "Schema snapshot file invalid for capability 'create-invoice' ({$path}): invalid JSON");
    });
});

it('fail: a snapshot file whose root is not a JSON object is rejected [D-020]', function () {
    withSnapshotFile('"just a string"', function (string $path): void {
        expect(fn () => SchemaSnapshot::loadFile('create-invoice', $path))
            ->toThrow(SchemaSnapshotException::class, 'root must be a JSON object');
    });
});

it('fail: a snapshot side that is neither an object nor null is rejected [D-020]', function () {
    withSnapshotFile('{"input_schema": "object"}', function (string $path): void {
        expect(fn () => SchemaSnapshot::loadFile('create-invoice', $path))
            ->toThrow(SchemaSnapshotException::class, 'Schema snapshot side must be a JSON object or null.');
    });
});

it('edge: a bare JSON Schema file locks only the input side; null sides are kept [D-020]', function () {
    $bare = withSnapshotFile('{"type": "object"}', fn (string $path) => SchemaSnapshot::loadFile('create-invoice', $path));
    $nullOutput = withSnapshotFile('{"output_schema": null}', fn (string $path) => SchemaSnapshot::loadFile('create-invoice', $path));

    expect($bare)->toBe(['input_schema' => ['type' => 'object']])
        ->and($nullOutput)->toBe(['output_schema' => null]);
});
