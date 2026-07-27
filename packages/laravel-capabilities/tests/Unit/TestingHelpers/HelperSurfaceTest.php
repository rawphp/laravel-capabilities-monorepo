<?php

// REQ-010 fleshed unit tests for TestingHelpers/HelperSurfaceTest.php. Unit-only, no database.

declare(strict_types=1);

use InvalidArgumentException;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;

it("happy: testing helper fake exists for package consumers [D-020]", function () {
    $h = CatalogHelpers::harness();
    expect($h['registry']->fake())->toBe($h['registry']);
});

it("happy: testing helper assertSchemaSnapshot exists for package consumers [D-020]", function () {
    $h = CatalogHelpers::harness();
    expect(method_exists($h['registry'], 'assertSchemaSnapshot'))->toBeTrue()
        ->and($h['registry']->assertSchemaSnapshot($h['name']))->toBeTrue();
});

it("happy: testing helper assertParity exists for package consumers [D-020]", function () {
    $h = CatalogHelpers::harness();
    expect(method_exists($h['registry'], 'assertParity'))->toBeTrue()
        ->and($h['registry']->assertParity())->toBeTrue();
});

it("happy: testing helper assertCannotInvokeAcrossTenant exists for package consumers [D-020]", function () {
    $h = CatalogHelpers::harness();
    expect(method_exists($h['registry'], 'assertCannotInvokeAcrossTenant'))->toBeTrue()
        ->and($h['registry']->assertCannotInvokeAcrossTenant())->toBeTrue();
});

it("happy: testing helper assertScopeResolvedTo exists for package consumers [D-020]", function () {
    $h = CatalogHelpers::harness();
    expect(method_exists($h['registry'], 'assertScopeResolvedTo'))->toBeTrue();
});

it("happy: testing helper assertLastScopeTenant exists for package consumers [D-020]", function () {
    $h = CatalogHelpers::harness();
    expect(method_exists($h['registry'], 'assertLastScopeTenant'))->toBeTrue();
});

it("happy: testing helper assertOk exists for package consumers [D-020]", function () {
    $r = CapabilityResult::ok(['x' => 1]);
    expect(method_exists($r, 'assertOk'))->toBeTrue()
        ->and($r->assertOk())->toBe($r);
});

it("happy: testing helper assertFailed exists for package consumers [D-020]", function () {
    $r = CapabilityResult::failure('domain_error', 'x');
    expect(method_exists($r, 'assertFailed'))->toBeTrue()
        ->and($r->assertFailed())->toBe($r);
});

it("happy: testing helper assertForbidden exists for package consumers [D-020]", function () {
    $r = CapabilityResult::failure('forbidden', 'no');
    expect($r->assertForbidden())->toBe($r);
});

it("happy: testing helper assertConflict exists for package consumers [D-020]", function () {
    $r = CapabilityResult::failure('conflict', 'c');
    expect($r->assertConflict())->toBe($r);
});

it("happy: testing helper assertExpired exists for package consumers [D-020]", function () {
    $r = CapabilityResult::failure('expired', 'e');
    expect($r->assertExpired())->toBe($r);
});
