<?php

// REQ-010 fleshed unit tests for Profiles/ProfileCompositionMatrixTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Profiles\ProfileRequiredException;
use Rawphp\Capabilities\Tests\Fixtures\ProfileHelpers;

it('happy: selection profile:billing returns only allowed tools [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $tools = $h['registry']->aiTools('billing');
    $names = array_column($tools, 'name');
    expect($names)->toContain('create-invoice')
        ->and($names)->toContain('void-invoice')
        ->and($names)->not->toContain('delete-account');
});

it('fail: selection profile:billing never returns tools outside selection [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools('billing'), 'name');
    expect($names)->not->toContain('delete-account');
});

it('fail: selection profile:billing still authorizes on invoke [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'authorizer' => ProfileHelpers::denyAuthorizer(),
    ]);
    // tool may be listed under billing
    $r = $h['registry']->invoke('create-invoice', ProfileHelpers::input(), ProfileHelpers::options('agent'));
    expect($r->isOk())->toBeFalse()->and($r->errorCode())->toBe('forbidden');
});

it('happy: selection profile:support returns only allowed tools [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools('support'), 'name');
    expect($names)->toContain('list-invoices')
        ->and($names)->not->toContain('void-invoice')
        ->and($names)->not->toContain('create-invoice');
});

it('fail: selection profile:support never returns tools outside selection [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools('support'), 'name');
    expect($names)->not->toContain('delete-account');
});

it('fail: selection profile:support still authorizes on invoke [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'authorizer' => ProfileHelpers::denyAuthorizer(),
    ]);
    // tool may be listed under billing
    $r = $h['registry']->invoke('create-invoice', ProfileHelpers::input(), ProfileHelpers::options('agent'));
    expect($r->isOk())->toBeFalse()->and($r->errorCode())->toBe('forbidden');
});

it('happy: selection groups:finance returns only allowed tools [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools('groups:finance'), 'name');
    expect($names)->toContain('create-invoice')
        ->and($names)->not->toContain('delete-account');
});

it('fail: selection groups:finance never returns tools outside selection [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools('groups:finance'), 'name');
    expect($names)->not->toContain('delete-account');
});

it('fail: selection groups:finance still authorizes on invoke [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'authorizer' => ProfileHelpers::denyAuthorizer(),
    ]);
    // tool may be listed under billing
    $r = $h['registry']->invoke('create-invoice', ProfileHelpers::input(), ProfileHelpers::options('agent'));
    expect($r->isOk())->toBeFalse()->and($r->errorCode())->toBe('forbidden');
});

it('happy: selection only:create-invoice returns only allowed tools [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools('only:create-invoice'), 'name');
    expect($names)->toBe(['create-invoice']);
});

it('fail: selection only:create-invoice never returns tools outside selection [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools('only:create-invoice'), 'name');
    expect($names)->not->toContain('delete-account');
});

it('fail: selection only:create-invoice still authorizes on invoke [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'authorizer' => ProfileHelpers::denyAuthorizer(),
    ]);
    // tool may be listed under billing
    $r = $h['registry']->invoke('create-invoice', ProfileHelpers::input(), ProfileHelpers::options('agent'));
    expect($r->isOk())->toBeFalse()->and($r->errorCode())->toBe('forbidden');
});

it('happy: selection only:void-invoice returns only allowed tools [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools('only:void-invoice'), 'name');
    expect($names)->toBe(['void-invoice']);
});

it('fail: selection only:void-invoice never returns tools outside selection [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools('only:void-invoice'), 'name');
    expect($names)->not->toContain('delete-account');
});

it('fail: selection only:void-invoice still authorizes on invoke [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'authorizer' => ProfileHelpers::denyAuthorizer(),
    ]);
    // tool may be listed under billing
    $r = $h['registry']->invoke('create-invoice', ProfileHelpers::input(), ProfileHelpers::options('agent'));
    expect($r->isOk())->toBeFalse()->and($r->errorCode())->toBe('forbidden');
});

it('fail: aiMetaTools without profile throws [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    expect(fn () => $h['registry']->aiMetaTools(null))
        ->toThrow(ProfileRequiredException::class);
});

it('happy: aiMetaTools with profile inherits allowlist [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $meta = $h['registry']->aiMetaTools('billing');
    expect($meta)->not->toBeEmpty()
        ->and($meta[0]['allowlist'] ?? [])->toContain('create-invoice')
        ->and($meta[0]['allowlist'] ?? [])->not->toContain('delete-account');
});

it('fail: aiMetaTools run outside profile blocked [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $before = $h['runs']['delete-account']->value;
    $r = $h['registry']->runCapabilityInProfile('agent', 'billing', 'delete-account', ProfileHelpers::input(), ProfileHelpers::options('agent'));
    expect($r->errorCode())->toBe('capability_not_in_profile')
        ->and($h['runs']['delete-account']->value)->toBe($before);
});

it('happy: aiMetaTools list outside profile excluded [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = $h['registry']->listCapabilitiesInProfile('agent', 'billing');
    expect($names)->not->toContain('delete-account')
        ->and($names)->toContain('create-invoice');
});

it('fail: mcpMetaTools without profile throws [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    expect(fn () => $h['registry']->mcpMetaTools(null))
        ->toThrow(ProfileRequiredException::class);
});

it('happy: mcpMetaTools with profile inherits allowlist [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $meta = $h['registry']->mcpMetaTools('billing');
    expect($meta)->not->toBeEmpty()
        ->and($meta[0]['allowlist'] ?? [])->toContain('create-invoice')
        ->and($meta[0]['allowlist'] ?? [])->not->toContain('delete-account');
});

it('fail: mcpMetaTools run outside profile blocked [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $before = $h['runs']['delete-account']->value;
    $r = $h['registry']->runCapabilityInProfile('mcp', 'billing', 'delete-account', ProfileHelpers::input(), ProfileHelpers::options('mcp'));
    expect($r->errorCode())->toBe('capability_not_in_profile')
        ->and($h['runs']['delete-account']->value)->toBe($before);
});

it('happy: mcpMetaTools list outside profile excluded [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = $h['registry']->listCapabilitiesInProfile('mcp', 'billing');
    expect($names)->not->toContain('delete-account')
        ->and($names)->toContain('create-invoice');
});
