<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;

it('fail: delegated client cannot widen profile via tool args [D-023]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('support');
    // Support profile excludes void-invoice; tool cannot request a wider profile via args.
    $r = $h['mcp']->handle(
        'void-invoice',
        AdapterHelpers::input(['profile' => 'billing']),
        McpCredential::userDelegated($h['user'], 'cursor-mcp'),
        ['profile' => 'support'],
    );
    expect($r->errorCode())->toBe('capability_not_in_profile')
        ->and($h['runs']['void-invoice']->value)->toBe(0);
});

it('fail: integration bot cannot act as arbitrary user id from tool args [D-023]', function () {
    $h = AdapterHelpers::harness([
        'mcp_auth' => [
            'allow_integration_credentials' => true,
            'integration_actors' => ['mcp-billing-service' => 'billing-bot'],
        ],
    ]);
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(['user_id' => 1, 'actor' => 'admin']),
        McpCredential::integration('mcp-billing-service'),
        ['profile' => 'billing'],
    );
    expect($r->isOk())->toBeFalse()->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('fail: user_pat cannot set client_id as actor authority [D-023]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(['client_id' => 'elevate-me']),
        McpCredential::userPat($h['user']),
        ['profile' => 'billing'],
    );
    // client_id in tool JSON is spoof — rejected
    expect($r->isOk())->toBeFalse()->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('happy: user_delegated records both user and client_id in audit [D-023]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $user = AdapterHelpers::user(11);
    $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::userDelegated($user, 'cursor-mcp'),
        ['profile' => 'billing'],
    );
    $entry = $h['fakes']->audit->all();
    $last = $entry[array_key_last($entry)];
    expect($last['actor_id'] ?? null)->not->toBeNull()
        ->and($last['mcp']['client_id'] ?? null)->toBe('cursor-mcp')
        ->and($last['mcp']['auth_profile'] ?? null)->toBe('user_delegated');
});

it('edge: host session metadata optional and non-authoritative for authz [D-023]', function () {
    $h = AdapterHelpers::harness(['authorizer' => AdapterHelpers::denyAuthorizer()]);
    $h['mcp']->register('billing');
    $cred = McpCredential::userDelegated(
        $h['user'],
        'cursor-mcp',
        host: 'cursor',
        session: ['host_os_user' => 'someone-else', 'elevated' => true],
    );
    $r = $h['mcp']->handle('create-invoice', AdapterHelpers::input(), $cred, ['profile' => 'billing']);
    // Session metadata is attached but does not bypass authorize.
    expect($r->errorCode())->toBe('forbidden')
        ->and($h['registry']->lastState()?->context?->mcp()['session']['elevated'] ?? null)->toBeTrue()
        ->and($h['runs']['create-invoice']->value)->toBe(0);
});
