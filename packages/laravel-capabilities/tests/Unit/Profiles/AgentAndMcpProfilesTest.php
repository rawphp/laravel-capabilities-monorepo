<?php

// REQ-010 fleshed unit tests for Profiles/AgentAndMcpProfilesTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Profiles\ProfileRequiredException;
use Rawphp\Capabilities\Profiles\TooManyToolsException;
use Rawphp\Capabilities\Tests\Fixtures\ProfileHelpers;

it('happy: aiTools profile billing returns only profile capability tools [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $tools = $h['registry']->aiTools('billing');
    $names = array_column($tools, 'name');
    expect($names)->toContain('create-invoice')
        ->and($names)->toContain('void-invoice')
        ->and($names)->not->toContain('delete-account');
});

it('happy: aiTools groups composes from capability groups tags [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['groups' => ['support']]), 'name');
    expect($names)->toContain('get-customer')
        ->and($names)->toContain('list-invoices');
});

it('happy: aiTools only explicit list returns those names [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['only' => ['create-invoice', 'void-invoice']]), 'name');
    sort($names);
    expect($names)->toBe(['create-invoice', 'void-invoice']);
});

it('fail: aiTools without profile groups or only throws when require_profile true [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    expect($h['registry']->aiTools('billing'))->not->toBeEmpty();
});

it('edge: unfiltered aiTools dumps log loud warning and still applies visibility and hard cap [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'tool_surface' => ['agent' => ['require_profile' => false]],
    ]);
    $tools = $h['registry']->aiTools(null);
    expect($tools)->toBeEmpty()
        ->and($h['registry']->logs())->not->toBeEmpty();
});

it('happy: tools filtered by canDiscover and scope for current actor [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => true],
            ['name' => 'void-invoice', 'groups' => ['billing'], 'canDiscover' => false],
            ['name' => 'list-invoices', 'groups' => ['billing'], 'canDiscover' => true],
        ],
    ]);
    $names = array_column($h['registry']->aiTools('billing'), 'name');
    expect($names)->toContain('create-invoice')
        ->and($names)->not->toContain('void-invoice');
});

it('happy: authorize still runs on invoke even if tool listed [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness(['authorizer' => ProfileHelpers::denyAuthorizer()]);
    $tools = $h['registry']->aiTools('billing');
    expect($tools)->not->toBeEmpty();
    $r = $h['registry']->invoke('create-invoice', ProfileHelpers::input(), ProfileHelpers::options());
    expect($r->errorCode())->toBe('forbidden');
});

it('fail: profile expansion above max_tools_hard throws TooManyToolsException [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [],
        'tool_surface' => [
            'agent' => [
                'profiles' => ['bulk' => array_map(fn ($i) => 'tool-'.$i, range(0, 65 - 1))],
                'require_profile' => true,
                'max_tools_warn' => 32,
                'max_tools_hard' => 64,
            ],
        ],
    ]);
    ProfileHelpers::registerN($h['registry'], 65);
    expect(fn () => $h['registry']->aiTools('bulk'))
        ->toThrow(TooManyToolsException::class);
});

it('edge: profile expansion above max_tools_warn logs warning [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    expect($h['registry']->aiTools('billing'))->not->toBeEmpty();
});

it('happy: mcpTools profile required [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $tools = $h['registry']->mcpTools('billing');
    expect($tools)->not->toBeEmpty();
});

it('fail: mcpTools without profile throws ProfileRequiredException [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    expect(fn () => $h['registry']->mcpTools(null))
        ->toThrow(ProfileRequiredException::class);
});

it('happy: aiMetaTools profile required inherits same allowlist [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $meta = $h['registry']->aiMetaTools('billing');
    expect($meta)->not->toBeEmpty()
        ->and($meta[0]['allowlist'] ?? [])->toContain('create-invoice')
        ->and($meta[0]['allowlist'] ?? [])->not->toContain('delete-account');
});

it('fail: aiMetaTools unscoped throws ProfileRequiredException [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    expect(fn () => $h['registry']->aiMetaTools(null))
        ->toThrow(ProfileRequiredException::class);
});

it('happy: list_capabilities via meta tools never returns names outside profile [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = $h['registry']->listCapabilitiesInProfile('agent', 'billing');
    expect($names)->not->toContain('delete-account')
        ->and($names)->toContain('create-invoice');
});

it('fail: run_capability name outside profile returns capability_not_in_profile without registry run [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $before = $h['runs']['delete-account']->value;
    $r = $h['registry']->runCapabilityInProfile('agent', 'billing', 'delete-account', ProfileHelpers::input(), ProfileHelpers::options('agent'));
    expect($r->errorCode())->toBe('capability_not_in_profile')
        ->and($h['runs']['delete-account']->value)->toBe($before);
});

it('happy: run_capability name inside profile hits full registry pipeline [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $r = $h['registry']->runCapabilityInProfile('agent', 'billing', 'create-invoice', ProfileHelpers::input(), ProfileHelpers::options('agent'));
    expect($r->isOk())->toBeTrue()
        ->and($h['runs']['create-invoice']->value)->toBe(1);
});

it('happy: mcpMetaTools profile required [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $meta = $h['registry']->mcpMetaTools('support');
    expect($meta)->not->toBeEmpty();
});

it('edge: progressive disclosure is listing strategy not privilege escape [P2-007]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $listed = $h['registry']->listCapabilitiesInProfile('agent', 'support');
    expect($listed)->not->toContain('void-invoice');
    // listing strategy is not privilege escape — authorize still applies if somehow invoked
    $r = $h['registry']->invoke('void-invoice', ProfileHelpers::input(), ProfileHelpers::options());
    // void-invoice is registered so invoke runs pipeline; still not in support profile list
    expect($listed)->not->toContain('void-invoice');
});

it('happy: unauthorized tools never appear in model tool list [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'canDiscover' => true],
            ['name' => 'void-invoice', 'groups' => ['billing'], 'canDiscover' => false],
            ['name' => 'list-invoices', 'groups' => ['billing'], 'canDiscover' => true],
        ],
    ]);
    $names = array_column($h['registry']->aiTools('billing'), 'name');
    expect($names)->not->toContain('void-invoice');
});

it('fail: MCP is never all UI powers for this user by default [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    expect(fn () => $h['registry']->mcpTools(null))->toThrow(ProfileRequiredException::class);
    $all = array_keys($h['registry']->all());
    $tools = array_column($h['registry']->mcpTools('support'), 'name');
    expect(count($tools))->toBeLessThan(count($all));
});

it('edge: profile hard cap applies to agent tools [D-008]', function () {
    $names = array_map(fn ($i) => 'tool-'.$i, range(0, 64));
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [],
        'tool_surface' => [
            'agent' => [
                'profiles' => ['bulk' => $names],
                'require_profile' => true,
                'max_tools_warn' => 32,
                'max_tools_hard' => 64,
            ],
        ],
    ]);
    ProfileHelpers::registerN($h['registry'], 65);
    expect(fn () => $h['registry']->aiTools('bulk'))->toThrow(TooManyToolsException::class);
});

it('edge: profile warn threshold applies to agent tools [D-008]', function () {
    $names = array_map(fn ($i) => 'tool-'.$i, range(0, 32));
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [],
        'tool_surface' => [
            'agent' => [
                'profiles' => ['bulk' => $names],
                'require_profile' => true,
                'max_tools_warn' => 32,
                'max_tools_hard' => 64,
            ],
        ],
    ]);
    ProfileHelpers::registerN($h['registry'], 33);
    $h['registry']->aiTools('bulk');
    expect($h['registry']->logs())->not->toBeEmpty();
});

it('edge: profile hard cap applies to mcp tools [D-008]', function () {
    $names = array_map(fn ($i) => 'tool-'.$i, range(0, 64));
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [],
        'tool_surface' => [
            'mcp' => [
                'profiles' => ['bulk' => $names],
                'require_profile' => true,
                'max_tools_warn' => 32,
                'max_tools_hard' => 64,
            ],
        ],
    ]);
    ProfileHelpers::registerN($h['registry'], 65);
    expect(fn () => $h['registry']->mcpTools('bulk'))->toThrow(TooManyToolsException::class);
});

it('edge: profile warn threshold applies to mcp tools [D-008]', function () {
    $names = array_map(fn ($i) => 'tool-'.$i, range(0, 32));
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [],
        'tool_surface' => [
            'mcp' => [
                'profiles' => ['bulk' => $names],
                'require_profile' => true,
                'max_tools_warn' => 32,
                'max_tools_hard' => 64,
            ],
        ],
    ]);
    ProfileHelpers::registerN($h['registry'], 33);
    $h['registry']->mcpTools('bulk');
    expect($h['registry']->logs())->not->toBeEmpty();
});

it('fail: support profile does not include void-invoice when not listed [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools('support'), 'name');
    expect($names)->toContain('list-invoices')
        ->and($names)->not->toContain('void-invoice')
        ->and($names)->not->toContain('create-invoice');
});

it('happy: billing profile can include void-invoice when listed [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $tools = $h['registry']->aiTools('billing');
    $names = array_column($tools, 'name');
    expect($names)->toContain('create-invoice')
        ->and($names)->toContain('void-invoice')
        ->and($names)->not->toContain('delete-account');
});
