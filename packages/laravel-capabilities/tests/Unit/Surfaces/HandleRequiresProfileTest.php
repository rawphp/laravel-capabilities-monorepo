<?php

// L-017 / D-008: tool execution obeys the same profile rule as the tool list. With
// require_profile (default true) an adapter that has no active or per-call profile refuses
// to run any capability by name instead of falling open to the full catalog.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;

it('fail: an unregistered agent adapter refuses handle() with profile_required and never runs [L-017 / D-008]', function () {
    $h = AdapterHelpers::harness();

    $r = $h['ai']->handle('delete-account', AdapterHelpers::input(), $h['user']);

    expect($r->errorCode())->toBe('not_runnable')
        ->and($r->error['normalized_code'])->toBe('profile_required')
        ->and($h['runs']['delete-account']->value)->toBe(0)
        ->and($h['registry']->lastState())->toBeNull();
});

it('fail: an unregistered MCP adapter refuses handle() with profile_required and never runs [L-017 / D-008]', function () {
    $h = AdapterHelpers::harness();

    $r = $h['mcp']->handle('delete-account', AdapterHelpers::input(), McpCredential::userPat($h['user']));
    $s = $h['mcp']->handleStructured('delete-account', AdapterHelpers::input(), McpCredential::userPat($h['user']));

    expect($r->errorCode())->toBe('not_runnable')
        ->and($r->error['normalized_code'])->toBe('profile_required')
        ->and($s['error']['code'])->toBe('profile_required')
        ->and($h['runs']['delete-account']->value)->toBe(0);
});

it('happy: a per-call profile option still runs inside that profile without register() [D-008]', function () {
    $h = AdapterHelpers::harness();

    $r = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], ['profile' => 'billing']);

    expect($r->isOk())->toBeTrue()->and($h['runs']['create-invoice']->value)->toBe(1);
});

it('edge: require_profile=false keeps the profile-less fallback for both adapters [L-017]', function () {
    $h = AdapterHelpers::harness(['require_profile' => false]);

    $ai = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user']);
    $mcp = $h['mcp']->handle('create-invoice', AdapterHelpers::input(), McpCredential::userPat($h['user']));

    expect($ai->isOk())->toBeTrue()
        ->and($mcp->isOk())->toBeTrue()
        ->and($h['runs']['create-invoice']->value)->toBe(2);
});
