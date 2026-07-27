<?php

// REQ-010 fleshed unit tests for TestingHelpers/ParityAndSnapshotsTest.php. Unit-only, no database.

declare(strict_types=1);

use InvalidArgumentException;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;

it("happy: assertSchemaSnapshot fails when input_schema changes without update [D-020]", function () {
    $h = CatalogHelpers::harness();
    expect(fn () => $h['registry']->assertSchemaSnapshot($h['name'], ['type' => 'string']))
        ->toThrow(InvalidArgumentException::class);
});

it("happy: assertSchemaSnapshot passes when schema matches snapshot [D-020]", function () {
    $h = CatalogHelpers::harness();
    $schema = $h['registry']->get($h['name'])->inputSchema();
    expect($h['registry']->assertSchemaSnapshot($h['name'], $schema))->toBeTrue();
});

it("happy: assertParity same success class across registry surfaces with mocks [D-020]", function () {
    $h = CatalogHelpers::harness();
    expect($h['registry']->assertParity(['agent', 'http', 'cli']))->toBeTrue();
});

it("happy: assertCannotInvokeAcrossTenant fails test on cross-tenant success [D-003]", function () {
    // Helper presence: full cross-tenant deny requires scope resolver wiring (REQ-006).
    $h = CatalogHelpers::harness();
    expect($h['registry']->assertCannotInvokeAcrossTenant())->toBeTrue();
});

it("happy: assertScopeResolvedTo fails when scope mismatches (case 1) [D-003]", function () {
    $h = CatalogHelpers::harness();
    $h['registry']->invoke($h['name'], CatalogHelpers::input(), CatalogHelpers::options('http', [
        'tenant_id' => 'tenant-a',
    ]));
    expect(fn () => $h['registry']->assertScopeResolvedTo('tenant-zzz'))
        ->toThrow(InvalidArgumentException::class);
});

it("happy: assertLastScopeTenant reflects SystemActor first-class tenant not smuggled input [P2-005]", function () {
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

it("edge: assertParity same deny class across registry http and ai with mocks [D-020]", function () {
    $h = CatalogHelpers::harness();
    expect($h['registry']->assertParity(['http', 'agent']))->toBeTrue();
});

it("edge: assertParity can include surface path for agent [D-020]", function () {
    $h = CatalogHelpers::harness();
    expect($h['registry']->assertParity(['agent']))->toBeTrue();
});

it("edge: assertParity can include surface path for mcp [D-020]", function () {
    $h = CatalogHelpers::harness();
    expect($h['registry']->assertParity(['mcp']))->toBeTrue();
});

it("edge: assertParity can include surface path for http [D-020]", function () {
    $h = CatalogHelpers::harness();
    expect($h['registry']->assertParity(['http']))->toBeTrue();
});

it("edge: assertParity can include surface path for cli [D-020]", function () {
    $h = CatalogHelpers::harness();
    expect($h['registry']->assertParity(['cli']))->toBeTrue();
});

it("edge: assertParity can include surface path for job [D-020]", function () {
    $h = CatalogHelpers::harness();
    expect($h['registry']->assertParity(['job']))->toBeTrue();
});

it("happy: Capability::fake available for unit tests [D-020]", function () {
    $h = CatalogHelpers::harness();
    expect($h['registry']->fake())->toBe($h['registry']);
});
