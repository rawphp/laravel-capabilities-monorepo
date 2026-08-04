<?php

// REQ-010 fleshed unit tests for Profiles/DiscoverabilityFilterTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ProfileHelpers;

it('happy: tool listed when surface=agent can_discover=True in_profile=True [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['agent', 'http']],
            ['name' => 'list-invoices', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['agent', 'http']],
            ['name' => 'get-customer', 'groups' => ['support'], 'canDiscover' => true, 'surfaces' => ['agent', 'http']],
        ],
    ]);
    $profile = 'billing';
    $names = array_column($h['registry']->aiTools($profile), 'name');
    expect(in_array('create-invoice', $names, true))->toBe(true);
});

it('happy: authorize still runs on invoke when surface=agent can_discover=True in_profile=True [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'authorizer' => ProfileHelpers::denyAuthorizer(),
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['agent', 'mcp', 'http']],
        ],
    ]);
    $r = $h['registry']->invoke('create-invoice', ProfileHelpers::input(), ProfileHelpers::options('agent'));
    expect($r->errorCode())->toBe('forbidden');
});

it('fail: tool not listed when surface=agent can_discover=True in_profile=False [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['agent', 'http']],
            ['name' => 'list-invoices', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['agent', 'http']],
            ['name' => 'get-customer', 'groups' => ['support'], 'canDiscover' => true, 'surfaces' => ['agent', 'http']],
        ],
    ]);
    $profile = 'support';
    $names = array_column($h['registry']->aiTools($profile), 'name');
    expect(in_array('create-invoice', $names, true))->toBe(false);
});

it('happy: authorize still runs on invoke when surface=agent can_discover=True in_profile=False [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'authorizer' => ProfileHelpers::denyAuthorizer(),
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['agent', 'mcp', 'http']],
        ],
    ]);
    $r = $h['registry']->invoke('create-invoice', ProfileHelpers::input(), ProfileHelpers::options('agent'));
    expect($r->errorCode())->toBe('forbidden');
});

it('fail: tool not listed when surface=agent can_discover=False in_profile=True [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => false, 'surfaces' => ['agent', 'http']],
            ['name' => 'list-invoices', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['agent', 'http']],
            ['name' => 'get-customer', 'groups' => ['support'], 'canDiscover' => true, 'surfaces' => ['agent', 'http']],
        ],
    ]);
    $profile = 'billing';
    $names = array_column($h['registry']->aiTools($profile), 'name');
    expect(in_array('create-invoice', $names, true))->toBe(false);
});

it('happy: authorize still runs on invoke when surface=agent can_discover=False in_profile=True [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'authorizer' => ProfileHelpers::denyAuthorizer(),
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => false, 'surfaces' => ['agent', 'mcp', 'http']],
        ],
    ]);
    $r = $h['registry']->invoke('create-invoice', ProfileHelpers::input(), ProfileHelpers::options('agent'));
    expect($r->errorCode())->toBe('forbidden');
});

it('fail: tool not listed when surface=agent can_discover=False in_profile=False [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => false, 'surfaces' => ['agent', 'http']],
            ['name' => 'list-invoices', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['agent', 'http']],
            ['name' => 'get-customer', 'groups' => ['support'], 'canDiscover' => true, 'surfaces' => ['agent', 'http']],
        ],
    ]);
    $profile = 'support';
    $names = array_column($h['registry']->aiTools($profile), 'name');
    expect(in_array('create-invoice', $names, true))->toBe(false);
});

it('happy: authorize still runs on invoke when surface=agent can_discover=False in_profile=False [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'authorizer' => ProfileHelpers::denyAuthorizer(),
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => false, 'surfaces' => ['agent', 'mcp', 'http']],
        ],
    ]);
    $r = $h['registry']->invoke('create-invoice', ProfileHelpers::input(), ProfileHelpers::options('agent'));
    expect($r->errorCode())->toBe('forbidden');
});

it('happy: tool listed when surface=mcp can_discover=True in_profile=True [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['mcp', 'http']],
            ['name' => 'list-invoices', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['mcp', 'http']],
            ['name' => 'get-customer', 'groups' => ['support'], 'canDiscover' => true, 'surfaces' => ['mcp', 'http']],
        ],
    ]);
    $profile = 'billing';
    $names = array_column($h['registry']->mcpTools($profile), 'name');
    expect(in_array('create-invoice', $names, true))->toBe(true);
});

it('happy: authorize still runs on invoke when surface=mcp can_discover=True in_profile=True [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'authorizer' => ProfileHelpers::denyAuthorizer(),
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['agent', 'mcp', 'http']],
        ],
    ]);
    $r = $h['registry']->invoke('create-invoice', ProfileHelpers::input(), ProfileHelpers::options('mcp'));
    expect($r->errorCode())->toBe('forbidden');
});

it('fail: tool not listed when surface=mcp can_discover=True in_profile=False [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['mcp', 'http']],
            ['name' => 'list-invoices', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['mcp', 'http']],
            ['name' => 'get-customer', 'groups' => ['support'], 'canDiscover' => true, 'surfaces' => ['mcp', 'http']],
        ],
    ]);
    $profile = 'support';
    $names = array_column($h['registry']->mcpTools($profile), 'name');
    expect(in_array('create-invoice', $names, true))->toBe(false);
});

it('happy: authorize still runs on invoke when surface=mcp can_discover=True in_profile=False [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'authorizer' => ProfileHelpers::denyAuthorizer(),
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['agent', 'mcp', 'http']],
        ],
    ]);
    $r = $h['registry']->invoke('create-invoice', ProfileHelpers::input(), ProfileHelpers::options('mcp'));
    expect($r->errorCode())->toBe('forbidden');
});

it('fail: tool not listed when surface=mcp can_discover=False in_profile=True [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => false, 'surfaces' => ['mcp', 'http']],
            ['name' => 'list-invoices', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['mcp', 'http']],
            ['name' => 'get-customer', 'groups' => ['support'], 'canDiscover' => true, 'surfaces' => ['mcp', 'http']],
        ],
    ]);
    $profile = 'billing';
    $names = array_column($h['registry']->mcpTools($profile), 'name');
    expect(in_array('create-invoice', $names, true))->toBe(false);
});

it('happy: authorize still runs on invoke when surface=mcp can_discover=False in_profile=True [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'authorizer' => ProfileHelpers::denyAuthorizer(),
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => false, 'surfaces' => ['agent', 'mcp', 'http']],
        ],
    ]);
    $r = $h['registry']->invoke('create-invoice', ProfileHelpers::input(), ProfileHelpers::options('mcp'));
    expect($r->errorCode())->toBe('forbidden');
});

it('fail: tool not listed when surface=mcp can_discover=False in_profile=False [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => false, 'surfaces' => ['mcp', 'http']],
            ['name' => 'list-invoices', 'groups' => ['billing'], 'canDiscover' => true, 'surfaces' => ['mcp', 'http']],
            ['name' => 'get-customer', 'groups' => ['support'], 'canDiscover' => true, 'surfaces' => ['mcp', 'http']],
        ],
    ]);
    $profile = 'support';
    $names = array_column($h['registry']->mcpTools($profile), 'name');
    expect(in_array('create-invoice', $names, true))->toBe(false);
});

it('happy: authorize still runs on invoke when surface=mcp can_discover=False in_profile=False [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'authorizer' => ProfileHelpers::denyAuthorizer(),
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => false, 'surfaces' => ['agent', 'mcp', 'http']],
        ],
    ]);
    $r = $h['registry']->invoke('create-invoice', ProfileHelpers::input(), ProfileHelpers::options('mcp'));
    expect($r->errorCode())->toBe('forbidden');
});
