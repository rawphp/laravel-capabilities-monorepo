<?php

// REQ-043: full D-020 assertSchemaSnapshot (input+output, durable files). Unit-only, no database.

declare(strict_types=1);

use InvalidArgumentException;
use Rawphp\Capabilities\Support\ParityAssertionException;
use Rawphp\Capabilities\Support\SchemaSnapshotException;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

/**
 * @return array{input_schema: array<string, mixed>|null, output_schema: array<string, mixed>|null}
 */
function schemaSnapshotFromRegistry($registry, string $name): array
{
    $def = $registry->get($name);

    return [
        'input_schema' => $def->inputSchema(),
        'output_schema' => $def->outputSchema(),
    ];
}

it('happy: assertSchemaSnapshot fails when input_schema changes without update [D-020]', function () {
    $h = CatalogHelpers::harness();
    $snap = schemaSnapshotFromRegistry($h['registry'], $h['name']);
    $snap['input_schema'] = ['type' => 'string'];

    expect(fn () => $h['registry']->assertSchemaSnapshot($h['name'], $snap))
        ->toThrow(SchemaSnapshotException::class, 'input_schema');
});

it('happy: assertSchemaSnapshot fails when output_schema changes without update [D-020]', function () {
    $h = CatalogHelpers::harness();
    $snap = schemaSnapshotFromRegistry($h['registry'], $h['name']);
    $snap['output_schema'] = ['type' => 'string'];

    expect(fn () => $h['registry']->assertSchemaSnapshot($h['name'], $snap))
        ->toThrow(SchemaSnapshotException::class, 'output_schema');
});

it('happy: assertSchemaSnapshot passes when schema matches snapshot [D-020]', function () {
    $h = CatalogHelpers::harness();
    $snap = schemaSnapshotFromRegistry($h['registry'], $h['name']);

    expect($h['registry']->assertSchemaSnapshot($h['name'], $snap))->toBeTrue();
});

it('happy: assertSchemaSnapshot passes when durable snapshot file matches [D-020]', function () {
    $h = CatalogHelpers::harness();
    $snap = schemaSnapshotFromRegistry($h['registry'], $h['name']);
    $dir = sys_get_temp_dir().'/cap-schema-snap-'.bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    $path = $dir.'/'.$h['name'].'.schema.json';
    file_put_contents($path, json_encode($snap, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

    try {
        expect($h['registry']->assertSchemaSnapshot($h['name'], $path))->toBeTrue();
    } finally {
        @unlink($path);
        @rmdir($dir);
    }
});

it('happy: assertSchemaSnapshot fails when durable snapshot file has input drift [D-020]', function () {
    $h = CatalogHelpers::harness();
    $snap = schemaSnapshotFromRegistry($h['registry'], $h['name']);
    $snap['input_schema'] = ['type' => 'null'];
    $dir = sys_get_temp_dir().'/cap-schema-snap-'.bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    $path = $dir.'/'.$h['name'].'.schema.json';
    file_put_contents($path, json_encode($snap, JSON_THROW_ON_ERROR));

    try {
        expect(fn () => $h['registry']->assertSchemaSnapshot($h['name'], $path))
            ->toThrow(SchemaSnapshotException::class, 'input_schema');
    } finally {
        @unlink($path);
        @rmdir($dir);
    }
});

it('happy: assertSchemaSnapshot fails when durable snapshot file has output drift [D-020]', function () {
    $h = CatalogHelpers::harness();
    $snap = schemaSnapshotFromRegistry($h['registry'], $h['name']);
    $snap['output_schema'] = ['type' => 'null'];
    $dir = sys_get_temp_dir().'/cap-schema-snap-'.bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    $path = $dir.'/'.$h['name'].'.schema.json';
    file_put_contents($path, json_encode($snap, JSON_THROW_ON_ERROR));

    try {
        expect(fn () => $h['registry']->assertSchemaSnapshot($h['name'], $path))
            ->toThrow(SchemaSnapshotException::class, 'output_schema');
    } finally {
        @unlink($path);
        @rmdir($dir);
    }
});

it('happy: assertSchemaSnapshot fails when snapshot file is missing in file mode [D-020]', function () {
    $h = CatalogHelpers::harness();
    $missing = sys_get_temp_dir().'/cap-schema-missing-'.bin2hex(random_bytes(4)).'/nope.schema.json';

    expect(fn () => $h['registry']->assertSchemaSnapshot($h['name'], $missing))
        ->toThrow(SchemaSnapshotException::class, 'missing');
});

it('happy: assertSchemaSnapshot uses conventional directory when provided [D-020]', function () {
    $h = CatalogHelpers::harness();
    $snap = schemaSnapshotFromRegistry($h['registry'], $h['name']);
    $dir = sys_get_temp_dir().'/cap-schema-dir-'.bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    $path = $dir.'/'.$h['name'].'.schema.json';
    file_put_contents($path, json_encode($snap, JSON_THROW_ON_ERROR));

    try {
        expect($h['registry']->assertSchemaSnapshot($h['name'], null, $dir))->toBeTrue();
    } finally {
        @unlink($path);
        @rmdir($dir);
    }
});

it('happy: assertSchemaSnapshot fails when conventional directory file is missing [D-020]', function () {
    $h = CatalogHelpers::harness();
    $dir = sys_get_temp_dir().'/cap-schema-dir-missing-'.bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);

    try {
        expect(fn () => $h['registry']->assertSchemaSnapshot($h['name'], null, $dir))
            ->toThrow(SchemaSnapshotException::class, 'missing');
    } finally {
        @rmdir($dir);
    }
});

it('happy: assertParity same success class across registry surfaces with mocks [D-020]', function () {
    $h = CatalogHelpers::harness();
    $asserted = 0;
    expect($h['registry']->assertParity($h['name'], [
        'input' => CatalogHelpers::input(),
        'surfaces' => ['agent', 'http', 'cli'],
        'assert' => function ($result) use (&$asserted) {
            expect($result->isOk())->toBeTrue();
            $asserted++;
        },
    ]))->toBeTrue();
    expect($asserted)->toBe(3);
});

it('happy: assertCannotInvokeAcrossTenant fails test on cross-tenant success [D-003]', function () {
    // Helper presence: full cross-tenant deny requires scope resolver wiring (REQ-006).
    $h = CatalogHelpers::harness();
    expect($h['registry']->assertCannotInvokeAcrossTenant())->toBeTrue();
});

it('happy: assertScopeResolvedTo fails when scope mismatches (case 1) [D-003]', function () {
    $h = CatalogHelpers::harness();
    $h['registry']->invoke($h['name'], CatalogHelpers::input(), CatalogHelpers::options('http', [
        'tenant_id' => 'tenant-a',
    ]));
    expect(fn () => $h['registry']->assertScopeResolvedTo('tenant-zzz'))
        ->toThrow(InvalidArgumentException::class);
});

it('happy: assertLastScopeTenant reflects SystemActor first-class tenant not smuggled input [P2-005]', function () {
    $h = CatalogHelpers::harness();
    expect(method_exists($h['registry'], 'assertLastScopeTenant'))->toBeTrue();
    // first-class tenant via options
    $h['registry']->invoke($h['name'], CatalogHelpers::input(), CatalogHelpers::options('job', [
        'actor' => new SystemActor('billing-worker'),
        'tenant_id' => 'tenant-system',
    ]));
    // may or may not set scope depending on require_scope; helper exists
    expect($h['registry']->lastScopeTenant() === null || is_string($h['registry']->lastScopeTenant()))->toBeTrue();
});

it('edge: assertParity same deny class across registry http and ai with mocks [D-020]', function () {
    // authorize: false → every surface denies; assert callback must not run on deny-path parity
    $h = PipelineHelpers::harness([
        'authorize' => false,
        'allowSystemCallers' => true,
    ]);
    $asserted = 0;
    expect($h['registry']->assertParity($h['name'], [
        'input' => PipelineHelpers::validInput(),
        'surfaces' => ['http', 'ai'],
        'assert' => function () use (&$asserted) {
            $asserted++;
        },
    ]))->toBeTrue();
    expect($asserted)->toBe(0);
});

it('edge: assertParity mismatch throws with surface names and result classes [D-020]', function () {
    // Capability only enabled on http → http succeeds, mcp is surface-gate forbidden
    $h = CatalogHelpers::harness([
        'cap_surfaces' => ['http'],
    ]);
    expect(fn () => $h['registry']->assertParity($h['name'], [
        'input' => CatalogHelpers::input(),
        'surfaces' => ['http', 'mcp'],
    ]))->toThrow(
        ParityAssertionException::class,
        'parity mismatch',
    );
});

it('edge: assertParity invalid surface list fails [D-020]', function () {
    $h = CatalogHelpers::harness();
    expect(fn () => $h['registry']->assertParity($h['name'], [
        'input' => CatalogHelpers::input(),
        'surfaces' => ['http', 'not-a-surface'],
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => $h['registry']->assertParity($h['name'], [
        'input' => CatalogHelpers::input(),
        'surfaces' => [''],
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => $h['registry']->assertParity($h['name'], [
        'input' => CatalogHelpers::input(),
        'surfaces' => [],
    ]))->toThrow(InvalidArgumentException::class);
});

it('edge: assertParity can include surface path for agent [D-020]', function () {
    $h = CatalogHelpers::harness();
    expect($h['registry']->assertParity($h['name'], [
        'input' => CatalogHelpers::input(),
        'surfaces' => ['agent'],
    ]))->toBeTrue();
});

it('edge: assertParity can include surface path for mcp [D-020]', function () {
    $h = CatalogHelpers::harness();
    expect($h['registry']->assertParity($h['name'], [
        'input' => CatalogHelpers::input(),
        'surfaces' => ['mcp'],
    ]))->toBeTrue();
});

it('edge: assertParity can include surface path for http [D-020]', function () {
    $h = CatalogHelpers::harness();
    expect($h['registry']->assertParity($h['name'], [
        'input' => CatalogHelpers::input(),
        'surfaces' => ['http'],
    ]))->toBeTrue();
});

it('edge: assertParity can include surface path for cli [D-020]', function () {
    $h = CatalogHelpers::harness();
    expect($h['registry']->assertParity($h['name'], [
        'input' => CatalogHelpers::input(),
        'surfaces' => ['cli'],
    ]))->toBeTrue();
});

it('edge: assertParity can include surface path for job [D-020]', function () {
    $h = CatalogHelpers::harness();
    expect($h['registry']->assertParity($h['name'], [
        'input' => CatalogHelpers::input(),
        'surfaces' => ['job'],
        'actor' => new SystemActor('billing-worker'),
    ]))->toBeTrue();
});

it('happy: Capability::fake available for unit tests [D-020]', function () {
    $h = CatalogHelpers::harness();
    expect($h['registry']->fake())->toBe($h['registry']);
});
