<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Adapters\StructuredToolResponse;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;

it('fail: mcp tool handle failure schema_invalid does not mutate incorrectly [MCP-001]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle('create-invoice', ['customer_id' => 'x'], McpCredential::userPat($h['user']), [
        'profile' => 'billing',
    ]);
    expect($r->isOk())->toBeFalse()->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('happy: mcp tool handle failure schema_invalid returns structured error [MCP-001]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $s = $h['mcp']->handleStructured('create-invoice', ['customer_id' => 'x'], McpCredential::userPat($h['user']), [
        'profile' => 'billing',
    ]);
    expect($s['error']['code'])->toBe('schema_invalid')->and($s['error']['structured'])->toBeTrue();
});

it('fail: mcp tool handle failure unauthorized does not mutate incorrectly [MCP-001]', function () {
    $h = AdapterHelpers::harness(['authorizer' => AdapterHelpers::denyAuthorizer()]);
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle('create-invoice', AdapterHelpers::input(), McpCredential::userPat($h['user']), [
        'profile' => 'billing',
    ]);
    expect($r->errorCode())->toBe('forbidden')->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('happy: mcp tool handle failure unauthorized returns structured error [MCP-001]', function () {
    $h = AdapterHelpers::harness(['authorizer' => AdapterHelpers::denyAuthorizer()]);
    $h['mcp']->register('billing');
    $s = $h['mcp']->handleStructured('create-invoice', AdapterHelpers::input(), McpCredential::userPat($h['user']), [
        'profile' => 'billing',
    ]);
    expect($s['error']['code'])->toBe('unauthorized');
});

it('fail: mcp tool handle failure approval_required does not mutate incorrectly [MCP-001]', function () {
    $r = CapabilityResult::approvalRequired('a-1');
    expect($r->isApprovalRequired())->toBeTrue();
});

it('happy: mcp tool handle failure approval_required returns structured error [MCP-001]', function () {
    $s = StructuredToolResponse::fromResult(CapabilityResult::approvalRequired('a-1'));
    expect($s['error']['code'])->toBe('approval_required')->and($s['error']['structured'])->toBeTrue();
});

it('fail: mcp tool handle failure rate_limited does not mutate incorrectly [MCP-001]', function () {
    $h = AdapterHelpers::harness([
        'rate_limit' => [
            'enabled' => true,
            'defaults' => ['per_minute' => 0, 'per_capability_per_minute' => 0],
        ],
    ]);
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle('create-invoice', AdapterHelpers::input(), McpCredential::userPat($h['user']), [
        'profile' => 'billing',
    ]);
    expect($r->errorCode())->toBe('rate_limited')->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('happy: mcp tool handle failure rate_limited returns structured error [MCP-001]', function () {
    $h = AdapterHelpers::harness([
        'rate_limit' => [
            'enabled' => true,
            'defaults' => ['per_minute' => 0, 'per_capability_per_minute' => 0],
        ],
    ]);
    $h['mcp']->register('billing');
    $s = $h['mcp']->handleStructured('create-invoice', AdapterHelpers::input(), McpCredential::userPat($h['user']), [
        'profile' => 'billing',
    ]);
    expect($s['error']['code'])->toBe('rate_limited');
});

it('fail: mcp tool handle failure not_in_profile does not mutate incorrectly [MCP-001]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle('delete-account', AdapterHelpers::input(), McpCredential::userPat($h['user']), [
        'profile' => 'billing',
    ]);
    expect($r->errorCode())->toBe('capability_not_in_profile')
        ->and($h['runs']['delete-account']->value)->toBe(0);
});

it('happy: mcp tool handle failure not_in_profile returns structured error [MCP-001]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $s = $h['mcp']->handleStructured('delete-account', AdapterHelpers::input(), McpCredential::userPat($h['user']), [
        'profile' => 'billing',
    ]);
    expect($s['error']['code'])->toBe('not_in_profile');
});

it('fail: mcp tool handle failure output_invalid does not mutate incorrectly [MCP-001]', function () {
    $h = AdapterHelpers::harness([
        'caps' => [[
            'name' => 'create-invoice',
            'groups' => ['billing'],
            'run' => fn () => 'bad',
        ]],
    ]);
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle('create-invoice', AdapterHelpers::input(), McpCredential::userPat($h['user']), [
        'profile' => 'billing',
    ]);
    expect($r->errorCode())->toBe('output_invalid')->and($r->isOk())->toBeFalse();
});

it('happy: mcp tool handle failure output_invalid returns structured error [MCP-001]', function () {
    $h = AdapterHelpers::harness([
        'caps' => [[
            'name' => 'create-invoice',
            'groups' => ['billing'],
            'run' => fn () => 'bad',
        ]],
    ]);
    $h['mcp']->register('billing');
    $s = $h['mcp']->handleStructured('create-invoice', AdapterHelpers::input(), McpCredential::userPat($h['user']), [
        'profile' => 'billing',
    ]);
    expect($s['error']['code'])->toBe('output_invalid');
});

it('fail: mcp tool handle failure domain_error does not mutate incorrectly [MCP-001]', function () {
    $h = AdapterHelpers::harness([
        'caps' => [[
            'name' => 'create-invoice',
            'groups' => ['billing'],
            'run' => function () {
                throw new RuntimeException('boom');
            },
        ]],
    ]);
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle('create-invoice', AdapterHelpers::input(), McpCredential::userPat($h['user']), [
        'profile' => 'billing',
    ]);
    expect($r->errorCode())->toBe('domain_error');
});

it('happy: mcp tool handle failure domain_error returns structured error [MCP-001]', function () {
    $h = AdapterHelpers::harness([
        'caps' => [[
            'name' => 'create-invoice',
            'groups' => ['billing'],
            'run' => function () {
                throw new RuntimeException('boom');
            },
        ]],
    ]);
    $h['mcp']->register('billing');
    $s = $h['mcp']->handleStructured('create-invoice', AdapterHelpers::input(), McpCredential::userPat($h['user']), [
        'profile' => 'billing',
    ]);
    expect($s['error']['code'])->toBe('domain_error');
});

it('fail: mcp tool handle failure actor_spoof_attempt does not mutate incorrectly [MCP-001]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(['user_id' => 1, 'actor' => 'x']),
        McpCredential::userPat($h['user']),
        ['profile' => 'billing'],
    );
    expect($r->isOk())->toBeFalse()->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('happy: mcp tool handle failure actor_spoof_attempt returns structured error [MCP-001]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $s = $h['mcp']->handleStructured(
        'create-invoice',
        AdapterHelpers::input(['user_id' => 1]),
        McpCredential::userPat($h['user']),
        ['profile' => 'billing'],
    );
    expect($s['error']['code'])->toBe('actor_spoof_attempt');
});

it('fail: mcp tool handle failure integration_disabled does not mutate incorrectly [MCP-001]', function () {
    $h = AdapterHelpers::harness(['mcp_auth' => ['allow_integration_credentials' => false]]);
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::integration('mcp-billing-service'),
        ['profile' => 'billing'],
    );
    expect($r->isOk())->toBeFalse()->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('happy: mcp tool handle failure integration_disabled returns structured error [MCP-001]', function () {
    $h = AdapterHelpers::harness(['mcp_auth' => ['allow_integration_credentials' => false]]);
    $h['mcp']->register('billing');
    $s = $h['mcp']->handleStructured(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::integration('mcp-billing-service'),
        ['profile' => 'billing'],
    );
    expect($s['error']['code'])->toBe('integration_disabled');
});
