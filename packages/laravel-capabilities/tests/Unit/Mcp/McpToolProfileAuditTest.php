<?php

// Audit records which D-008 tool profile authorized an MCP call, beside the D-023 auth profile.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;

it('happy: audit mcp block records the registered tool profile [D-008][D-023]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');

    $r = $h['mcp']->handle('create-invoice', AdapterHelpers::input(), McpCredential::userPat(AdapterHelpers::user(7)));

    $audit = $h['fakes']->audit->all();
    expect($r->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->context?->mcp()['tool_profile'] ?? null)->toBe('billing')
        ->and($audit[array_key_last($audit)]['mcp'])->toBe([
            'auth_profile' => 'user_pat',
            'tool_profile' => 'billing',
        ]);
});

it('happy: audit mcp block records a per-call profile override [D-008]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('other');

    $h['mcp']->handle('create-invoice', AdapterHelpers::input(), McpCredential::userDelegated(AdapterHelpers::user(), 'cursor-mcp'), [
        'profile' => 'billing',
    ]);

    $audit = $h['fakes']->audit->all();
    expect($audit[array_key_last($audit)]['mcp'])->toMatchArray([
        'auth_profile' => 'user_delegated',
        'client_id' => 'cursor-mcp',
        'tool_profile' => 'billing',
    ]);
});

it('edge: options mcp meta cannot override the adapter tool profile [D-008][D-023]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');

    $r = $h['mcp']->handle('create-invoice', AdapterHelpers::input(), McpCredential::userPat(AdapterHelpers::user()), [
        'profile' => 'billing',
        'mcp' => ['tool_profile' => 'spoofed'],
    ]);

    expect($r->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->context?->mcp()['tool_profile'] ?? null)->toBe('billing');
});
