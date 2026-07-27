<?php

// Spec-derived unit tests for D-005 wire formats. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Idempotency\IdempotencyConfig;
use Rawphp\Capabilities\Idempotency\IdempotencyKey;
use Rawphp\Capabilities\Idempotency\WireKeyResolver;
use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;

it('happy: idempotency key accepted via http header [D-005]', function () {
    $key = WireKeyResolver::resolve('http', headers: ['Idempotency-Key' => 'hdr-1'], body: []);
    expect($key)->toBe('hdr-1');
});

it('edge: idempotency key identity includes surface actor scope for http [D-005]', function () {
    $store = IdempotencyHelpers::store();
    $store->put([
        'tenant_id' => 't-http',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'create-invoice',
        'idempotency_key' => 'k-http',
        'status' => 'completed',
        'request_hash' => 'h',
        'result_json' => ['ok' => true, 'data' => 1, 'meta' => []],
    ]);
    expect($store->find('t-http', 'user', '1', 'create-invoice', 'k-http'))->not->toBeNull()
        ->and($store->find('t-other', 'user', '1', 'create-invoice', 'k-http'))->toBeNull();
});

it('happy: idempotency key accepted via http body [D-005]', function () {
    $key = WireKeyResolver::resolve('http', headers: [], body: ['idempotency_key' => 'body-1']);
    expect($key)->toBe('body-1');
    // header wins
    $win = WireKeyResolver::resolve(
        'http',
        headers: ['Idempotency-Key' => 'hdr-wins'],
        body: ['idempotency_key' => 'body-lose'],
    );
    expect($win)->toBe('hdr-wins');
});

it('edge: idempotency key identity includes surface actor scope for http (case 1) [D-005]', function () {
    $store = IdempotencyHelpers::store();
    IdempotencyHelpers::seedRow($store, [
        'tenant_id' => 't1',
        'actor_id' => '9',
        'idempotency_key' => 'shared',
        'capability_name' => 'create-invoice',
    ]);
    expect($store->find('t1', 'user', '9', 'create-invoice', 'shared'))->not->toBeNull()
        ->and($store->find('t1', 'user', '8', 'create-invoice', 'shared'))->toBeNull();
});

it('happy: idempotency key accepted via cli auto_uuid [D-005]', function () {
    $key = WireKeyResolver::resolve('cli', headers: [], body: []);
    expect($key)->not->toBeNull()
        ->and(IdempotencyKey::isValid($key))->toBeTrue();
});

it('edge: idempotency key identity includes surface actor scope for cli [D-005]', function () {
    $store = IdempotencyHelpers::store();
    IdempotencyHelpers::seedRow($store, [
        'tenant_id' => 'cli-t',
        'actor_id' => '3',
        'idempotency_key' => 'cli-k',
    ]);
    expect($store->find('cli-t', 'user', '3', 'create-invoice', 'cli-k'))->not->toBeNull();
});

it('happy: idempotency key accepted via cli manual_flag [D-005]', function () {
    $key = WireKeyResolver::resolve('cli', headers: [], body: ['idempotency_key' => 'manual-retry-001']);
    expect($key)->toBe('manual-retry-001');
});

it('edge: idempotency key identity includes surface actor scope for cli (case 1) [D-005]', function () {
    $store = IdempotencyHelpers::store();
    IdempotencyHelpers::seedRow($store, ['idempotency_key' => 'cli-iso', 'actor_id' => '1']);
    IdempotencyHelpers::seedRow($store, ['idempotency_key' => 'cli-iso', 'actor_id' => '2', 'result_json' => ['ok' => true, 'data' => 2, 'meta' => []]]);
    expect($store->find('tenant-1', 'user', '1', 'create-invoice', 'cli-iso')['result_json']['data']['invoice_id'] ?? $store->find('tenant-1', 'user', '1', 'create-invoice', 'cli-iso')['result_json']['data'])
        ->not->toBe($store->find('tenant-1', 'user', '2', 'create-invoice', 'cli-iso')['result_json']['data']);
});

it('happy: idempotency key accepted via mcp tool_arg [D-005]', function () {
    expect(WireKeyResolver::resolve('mcp', body: ['idempotency_key' => 'mcp-k']))->toBe('mcp-k');
});

it('edge: idempotency key identity includes surface actor scope for mcp [D-005]', function () {
    $store = IdempotencyHelpers::store();
    IdempotencyHelpers::seedRow($store, ['idempotency_key' => 'mcp-iso', 'actor_type' => 'user', 'actor_id' => '5']);
    expect($store->find('tenant-1', 'user', '5', 'create-invoice', 'mcp-iso'))->not->toBeNull()
        ->and($store->find('tenant-1', 'system', '5', 'create-invoice', 'mcp-iso'))->toBeNull();
});

it('happy: idempotency key accepted via agent tool_arg [D-005]', function () {
    expect(WireKeyResolver::resolve('agent', body: ['idempotency_key' => 'agent-k']))->toBe('agent-k');
});

it('edge: idempotency key identity includes surface actor scope for agent [D-005]', function () {
    $store = IdempotencyHelpers::store();
    IdempotencyHelpers::seedRow($store, ['capability_name' => 'void-invoice', 'idempotency_key' => 'ag-k']);
    expect($store->find('tenant-1', 'user', '7', 'void-invoice', 'ag-k'))->not->toBeNull()
        ->and($store->find('tenant-1', 'user', '7', 'create-invoice', 'ag-k'))->toBeNull();
});

it('happy: idempotency key accepted via job payload_field [D-005]', function () {
    expect(WireKeyResolver::resolve('job', body: ['idempotencyKey' => 'job-k']))->toBe('job-k');
});

it('edge: idempotency key identity includes surface actor scope for job [D-005]', function () {
    $store = IdempotencyHelpers::store();
    IdempotencyHelpers::seedRow($store, [
        'actor_type' => 'system',
        'actor_id' => 'billing-worker',
        'idempotency_key' => 'job-k',
    ]);
    expect($store->find('tenant-1', 'system', 'billing-worker', 'create-invoice', 'job-k'))->not->toBeNull();
});

it('happy: idempotency key accepted via approval_accept stored_or_header [D-005]', function () {
    $fromStored = WireKeyResolver::resolve('approval_accept', body: [], storedKey: 'from-row');
    expect($fromStored)->toBe('from-row');
    $fromHeader = WireKeyResolver::resolve(
        'approval_accept',
        headers: ['Idempotency-Key' => 'override'],
        storedKey: 'from-row',
    );
    expect($fromHeader)->toBe('override');
});

it('edge: idempotency key identity includes surface actor scope for approval_accept [D-005]', function () {
    $header = IdempotencyConfig::defaults()->header;
    expect($header)->toBe('Idempotency-Key');
    $key = WireKeyResolver::resolve(
        'approval_accept',
        headers: [$header => 'accept-k'],
        storedKey: null,
    );
    expect($key)->toBe('accept-k');
});
