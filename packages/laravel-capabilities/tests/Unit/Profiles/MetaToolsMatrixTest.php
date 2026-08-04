<?php

// REQ-010 fleshed unit tests for Profiles/MetaToolsMatrixTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ProfileHelpers;

it('happy: list may include when aiMetaTools action=list in_profile=True [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = $h['registry']->listCapabilitiesInProfile('agent', 'billing');
    expect($names)->toContain('create-invoice');
});

it('happy: run hits registry when aiMetaTools action=run in_profile=True [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $r = $h['registry']->runCapabilityInProfile('agent', 'billing', 'create-invoice', ProfileHelpers::input(), ProfileHelpers::options('agent'));
    expect($r->isOk())->toBeTrue()
        ->and($h['runs']['create-invoice']->value)->toBe(1);
});

it('fail: list excludes when aiMetaTools action=list in_profile=False [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = $h['registry']->listCapabilitiesInProfile('agent', 'billing');
    expect($names)->not->toContain('delete-account')
        ->and($names)->toContain('create-invoice');
});

it('fail: run blocked without registry run when aiMetaTools action=run in_profile=False [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $before = $h['runs']['delete-account']->value;
    $r = $h['registry']->runCapabilityInProfile('agent', 'billing', 'delete-account', ProfileHelpers::input(), ProfileHelpers::options('agent'));
    expect($r->errorCode())->toBe('capability_not_in_profile')
        ->and($h['runs']['delete-account']->value)->toBe($before);
});

it('happy: list may include when mcpMetaTools action=list in_profile=True [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = $h['registry']->listCapabilitiesInProfile('mcp', 'billing');
    expect($names)->toContain('create-invoice');
});

it('happy: run hits registry when mcpMetaTools action=run in_profile=True [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $r = $h['registry']->runCapabilityInProfile('mcp', 'billing', 'create-invoice', ProfileHelpers::input(), ProfileHelpers::options('mcp'));
    expect($r->isOk())->toBeTrue()
        ->and($h['runs']['create-invoice']->value)->toBe(1);
});

it('fail: list excludes when mcpMetaTools action=list in_profile=False [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = $h['registry']->listCapabilitiesInProfile('mcp', 'billing');
    expect($names)->not->toContain('delete-account')
        ->and($names)->toContain('create-invoice');
});

it('fail: run blocked without registry run when mcpMetaTools action=run in_profile=False [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $before = $h['runs']['delete-account']->value;
    $r = $h['registry']->runCapabilityInProfile('mcp', 'billing', 'delete-account', ProfileHelpers::input(), ProfileHelpers::options('mcp'));
    expect($r->errorCode())->toBe('capability_not_in_profile')
        ->and($h['runs']['delete-account']->value)->toBe($before);
});
