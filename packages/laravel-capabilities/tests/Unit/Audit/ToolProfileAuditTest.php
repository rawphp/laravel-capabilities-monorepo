<?php

// Audit records the tool profile an invoke was gated under (D-008 x D-010). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;
use Rawphp\Capabilities\Tests\Fixtures\ProfileHelpers;

function lastAuditEntry(array $fakes): array
{
    $all = $fakes['fakes']->audit->all();

    return $all[array_key_last($all)] ?? [];
}

it('happy: agent adapter invoke under profile records tool_profile [D-008][D-010]', function () {
    $h = AdapterHelpers::harness();
    $r = $h['ai']->handle('create-invoice', AdapterHelpers::input(), AdapterHelpers::user(7), ['profile' => 'billing']);
    expect($r->isOk())->toBeTrue()
        ->and(lastAuditEntry($h)['tool_profile'] ?? null)->toBe('billing');
});

it('happy: mcp adapter invoke records tool_profile beside mcp auth_profile [D-008][D-023]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle('create-invoice', AdapterHelpers::input(), McpCredential::userPat(AdapterHelpers::user(7)));
    $entry = lastAuditEntry($h);
    expect($r->isOk())->toBeTrue()
        ->and($entry['tool_profile'] ?? null)->toBe('billing')
        ->and($entry['mcp']['auth_profile'] ?? null)->toBe('user_pat');
});

it('fail: failed invoke under profile still records tool_profile [D-010]', function () {
    $h = ProfileHelpers::multiCapHarness(['authorize' => false]);
    $r = $h['registry']->runCapabilityInProfile('agent', 'billing', 'create-invoice', ProfileHelpers::input(), ProfileHelpers::options('agent'));
    $entry = lastAuditEntry($h);
    expect($r->isOk())->toBeFalse()
        ->and($entry['event'] ?? null)->toBe('capability.failed')
        ->and($entry['tool_profile'] ?? null)->toBe('billing');
});

it('edge: enforced profile overwrites a caller-supplied tool_profile option [D-022]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $r = $h['registry']->runCapabilityInProfile(
        'agent',
        'billing',
        'create-invoice',
        ProfileHelpers::input(),
        ProfileHelpers::options('agent', ['tool_profile' => 'admin']),
    );
    expect($r->isOk())->toBeTrue()
        ->and(lastAuditEntry($h)['tool_profile'] ?? null)->toBe('billing');
});

it('edge: invoke outside a tool profile records tool_profile null [D-010]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $r = $h['registry']->invoke('create-invoice', ProfileHelpers::input(), ProfileHelpers::options('http'));
    $entry = lastAuditEntry($h);
    expect($r->isOk())->toBeTrue()
        ->and($entry)->toHaveKey('tool_profile')
        ->and($entry['tool_profile'])->toBeNull();
});
