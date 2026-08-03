<?php

// REQ-010 fleshed unit tests for Profiles/SelectionKindsTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Profiles\ProfileRequiredException;
use Rawphp\Capabilities\Tests\Fixtures\ProfileHelpers;

it('edge: selection profile resolved to tool set [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools('profile:billing'), 'name');
    expect($names)->toContain('create-invoice');
});

it('fail: selection profile cannot escape allowlist [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools('profile:billing'), 'name');
    expect($names)->not->toContain('delete-account');
});

it('edge: selection groups resolved to tool set [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['groups' => ['ops']]), 'name');
    expect($names)->toContain('delete-account');
});

it('fail: selection groups cannot escape allowlist [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['groups' => ['ops']]), 'name');
    expect($names)->not->toContain('create-invoice');
});

it('edge: selection only resolved to tool set [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['only' => ['get-customer']]), 'name');
    expect($names)->toBe(['get-customer']);
});

it('fail: selection only cannot escape allowlist [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['only' => ['get-customer']]), 'name');
    expect($names)->not->toContain('create-invoice');
});

it('fail: selection none throws when require_profile [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    expect(fn () => $h['registry']->aiTools(null))
        ->toThrow(ProfileRequiredException::class);
});

it('edge: selection profile+groups resolved to tool set [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['profile' => 'billing', 'groups' => ['support']]), 'name');
    // allowlist from profile OR groups match
    expect($names)->not->toBeEmpty();
});

it('fail: selection profile+groups cannot escape allowlist [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['profile' => 'billing', 'groups' => ['support']]), 'name');
    // allowlist from profile OR groups match
    expect($names)->not->toBeEmpty();
});

it('edge: selection only+profile_conflict resolved to tool set [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['profile' => 'billing', 'only' => ['create-invoice']]), 'name');
    expect($names)->toBe(['create-invoice']);
});

it('fail: selection only+profile_conflict cannot escape allowlist [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['profile' => 'billing', 'only' => ['create-invoice']]), 'name');
    expect($names)->toBe(['create-invoice']);
});
