<?php

// REQ-014: Clients config matrix (D-022). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Http\CallerDeriver;

it("edge: token ability mapping for 'capabilities:cli' [D-022]", function () {
    expect(CapabilitiesConfig::mapTokenAbility('capabilities:cli'))->toBe('cli');
    $d = new CallerDeriver(CapabilitiesConfig::defaults()['clients']);
    expect($d->deriveFromCredential(['token_abilities' => ['capabilities:cli']]))->toBe('cli');
});

it("edge: token ability mapping for 'capabilities:admin' [D-022]", function () {
    $clients = CapabilitiesConfig::defaults()['clients'];
    $clients['token_abilities']['capabilities:admin'] = 'http';
    expect(CapabilitiesConfig::mapTokenAbility('capabilities:admin', $clients))->toBe('http');
});

it("edge: token ability mapping for 'unmapped' [D-022]", function () {
    expect(CapabilitiesConfig::mapTokenAbility('unmapped'))->toBeNull();
    $d = new CallerDeriver(CapabilitiesConfig::defaults()['clients']);
    expect($d->deriveFromCredential(['token_abilities' => ['something:else']]))->toBe('http');
});

it("edge: token ability mapping for '' [D-022]", function () {
    expect(CapabilitiesConfig::mapTokenAbility(''))->toBeNull();
});

it('edge: oauth client mapping for capabilities-cli [D-022]', function () {
    $clients = ['oauth' => ['capabilities-cli' => 'cli'], 'token_abilities' => []];
    expect(CapabilitiesConfig::mapOauthClient('capabilities-cli', $clients))->toBe('cli');
    $d = new CallerDeriver($clients);
    expect($d->deriveFromCredential(['oauth_client_id' => 'capabilities-cli']))->toBe('cli');
});

it('edge: oauth client mapping for ios-app [D-022]', function () {
    $clients = ['oauth' => ['ios-app' => 'http']];
    expect(CapabilitiesConfig::mapOauthClient('ios-app', $clients))->toBe('http');
});

it('edge: oauth client mapping for billing-integration [D-022]', function () {
    $clients = ['oauth' => ['billing-integration' => 'mcp']];
    expect(CapabilitiesConfig::mapOauthClient('billing-integration', $clients))->toBe('mcp');
});

it('edge: oauth client mapping for unknown [D-022]', function () {
    expect(CapabilitiesConfig::mapOauthClient('unknown'))->toBeNull();
    $d = new CallerDeriver(CapabilitiesConfig::defaults()['clients']);
    expect($d->deriveFromCredential(['oauth_client_id' => 'unknown-client-xyz']))->toBe('http');
});

it('happy: reject_upgrade_attempts=True behavior defined [D-022]', function () {
    $d = new CallerDeriver([
        'reject_upgrade_attempts' => true,
        'privilege_order' => ['http', 'cli', 'mcp', 'agent', 'job'],
    ]);
    $r = $d->resolve(['server_caller' => 'cli'], 'http');
    expect($r)->toHaveKeys(['caller', 'rejected']);
    expect(in_array($r['caller'], ['cli', 'http'], true))->toBeTrue();
});

it('happy: reject_upgrade_attempts=False behavior defined [D-022]', function () {
    $d = new CallerDeriver([
        'reject_upgrade_attempts' => false,
        'privilege_order' => ['http', 'cli', 'mcp', 'agent', 'job'],
    ]);
    $r = $d->resolve(['server_caller' => 'cli'], 'agent');
    expect($r['rejected'])->toBeFalse();
    expect(in_array($r['caller'], ['cli', 'agent'], true))->toBeTrue();
});
