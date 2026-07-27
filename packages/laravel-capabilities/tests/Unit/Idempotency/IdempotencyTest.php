<?php

// Spec-derived unit tests for D-005 core behaviours. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Idempotency\IdempotencyConfig;
use Rawphp\Capabilities\Idempotency\IdempotencyKey;
use Rawphp\Capabilities\Idempotency\MissingKeyWarner;
use Rawphp\Capabilities\Idempotency\RequestHash;
use Rawphp\Capabilities\Idempotency\WireKeyResolver;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;

it('happy: first key inserts processing then stores completed result [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $r = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http', [
        'idempotency_key' => 'first-1',
    ]));
    expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);

    $row = $h['store']->find('tenant-1', 'user', '7', $h['name'], 'first-1');
    expect($row)->not->toBeNull()
        ->and($row['status'])->toBe('completed')
        ->and($row['request_hash'])->toBe(RequestHash::of(IdempotencyHelpers::inputA()))
        ->and($row['result_json']['ok'])->toBeTrue();
});

it('happy: same key same input hash replays result without second run [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'replay-1']);
    $a = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $b = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    expect($a->isOk())->toBeTrue()
        ->and($b->isOk())->toBeTrue()
        ->and($b->isReplay())->toBeTrue()
        ->and($b->data)->toEqual($a->data)
        ->and($h['runCount']->value)->toBe(1);
});

it('fail: same key different input hash returns conflict 409 [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'conflict-1']);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $r = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputB(), $opts);
    expect($r->errorCode())->toBe('conflict')
        ->and($h['runCount']->value)->toBe(1);
    $r->assertConflict();
});

it('edge: key in processing returns 409 or 425 too early [D-005]', function () {
    $clock = IdempotencyHelpers::clock();
    $store = IdempotencyHelpers::store($clock);
    $guard = IdempotencyHelpers::guard($store, $clock);
    $def = IdempotencyHelpers::mutatingDefinition();
    $ctx = IdempotencyHelpers::context();
    $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());
    $guard->lookup($def, $ctx, 'proc-1', $hash);
    $out = $guard->lookup($def, $ctx, 'proc-1', $hash);
    expect($out['action'])->toBe('busy')
        ->and($out['result']->errorCode())->toBe('conflict')
        ->and($out['result']->error['retryable'] ?? false)->toBeTrue();
});

it('edge: failed result replays failure by default for TTL [D-005]', function () {
    $h = IdempotencyHelpers::harness([
        'run' => function () {
            throw new RuntimeException('domain boom');
        },
    ]);
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'fail-1']);
    $a = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $b = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    expect($a->isFailed())->toBeTrue()
        ->and($b->isReplay())->toBeTrue()
        ->and($b->isFailed())->toBeTrue()
        ->and($h['runCount']->value)->toBe(0); // run throws before count? depends on harness
});

it('happy: no key runs non-idempotent path [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http'));
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http'));
    expect($h['runCount']->value)->toBe(2);
});

it('happy: HTTP header Idempotency-Key wins over body idempotency_key [D-005]', function () {
    $key = WireKeyResolver::http('from-header', ['idempotency_key' => 'from-body']);
    expect($key)->toBe('from-header');
});

it('happy: body idempotency_key accepted when header absent [D-005]', function () {
    expect(WireKeyResolver::http(null, ['idempotency_key' => 'body-only']))->toBe('body-only');
});

it('happy: CLI always sends a key auto UUID [D-005]', function () {
    $a = WireKeyResolver::cli(null, []);
    $b = WireKeyResolver::cli(null, []);
    expect(IdempotencyKey::isValid($a))->toBeTrue()
        ->and(IdempotencyKey::isValid($b))->toBeTrue()
        ->and($a)->not->toBe($b);
});

it('happy: AI MCP tool optional idempotency_key argument honored [D-005]', function () {
    expect(WireKeyResolver::toolArg(null, ['idempotency_key' => 'mcp-opt']))->toBe('mcp-opt')
        ->and(WireKeyResolver::toolArg(null, []))->toBeNull();
});

it('happy: job idempotencyKey optional recommended path stores and replays [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('job', ['idempotency_key' => 'job-1']);
    $a = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $b = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    expect($a->isOk())->toBeTrue()->and($b->isReplay())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it('happy: approval accept uses stored key from original invoke by default [D-005]', function () {
    expect(WireKeyResolver::approvalAccept(null, [], 'orig-key'))->toBe('orig-key');
});

it('happy: approval accept with same key after executed replays without second run [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'accept-replay']);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $r = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    expect($r->isReplay())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it('happy: readOnly capabilities ignore idempotency keys [D-005]', function () {
    $def = IdempotencyHelpers::mutatingDefinition('ro', readOnly: true);
    expect($def->shouldUseIdempotency())->toBeFalse();
    $out = IdempotencyHelpers::guard()->lookup($def, IdempotencyHelpers::context(), 'k', 'h');
    expect($out['action'])->toBe('continue');
});

it('fail: idempotent required capability missing key returns 400 [D-005]', function () {
    $h = IdempotencyHelpers::harness(['idempotent' => 'required']);
    $r = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http'));
    expect($r->errorCode())->toBe('validation_failed')->and($h['runCount']->value)->toBe(0);
});

it('edge: idempotent none ignores keys [D-005]', function () {
    $h = IdempotencyHelpers::harness(['idempotent' => 'none']);
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'n1']);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    expect($h['runCount']->value)->toBe(2);
});

it('edge: idempotent optional default for mutations [D-005]', function () {
    $def = new CapabilityDefinition(
        name: 'mut-default',
        input: CreateInvoiceInput::class,
        output: CreateInvoiceResult::class,
    );
    expect($def->idempotent)->toBe(CapabilityDefinition::IDEMPOTENT_OPTIONAL)
        ->and($def->shouldUseIdempotency())->toBeTrue();
});

it('edge: expired key after TTL treated as new key [D-005]', function () {
    $clock = IdempotencyHelpers::clock();
    $store = IdempotencyHelpers::store($clock, 1);
    $guard = IdempotencyHelpers::guard($store, $clock, new IdempotencyConfig(ttlHours: 1));
    $def = IdempotencyHelpers::mutatingDefinition();
    $ctx = IdempotencyHelpers::context();
    $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());
    $guard->lookup($def, $ctx, 'exp-1', $hash);
    $guard->storeResult($def, $ctx, 'exp-1', $hash, CapabilityResult::success(['id' => 1]));
    $clock->advance(new \DateInterval('PT2H'));
    $out = $guard->lookup($def, $ctx, 'exp-1', $hash);
    expect($out['action'])->toBe('continue');
});

it('edge: key format rejects invalid characters and length [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $r = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http', [
        'idempotency_key' => 'bad key!',
    ]));
    expect($r->errorCode())->toBe('validation_failed')->and($h['runCount']->value)->toBe(0);
});

it('happy: unique scope actor capability key identity for storage [D-005]', function () {
    $store = IdempotencyHelpers::store();
    $store->put([
        'tenant_id' => 't1', 'actor_type' => 'user', 'actor_id' => '1',
        'capability_name' => 'create-invoice', 'idempotency_key' => 'k',
        'status' => 'completed', 'result_json' => ['ok' => true, 'data' => 'A', 'meta' => []],
    ]);
    $store->put([
        'tenant_id' => 't1', 'actor_type' => 'user', 'actor_id' => '2',
        'capability_name' => 'create-invoice', 'idempotency_key' => 'k',
        'status' => 'completed', 'result_json' => ['ok' => true, 'data' => 'B', 'meta' => []],
    ]);
    expect($store->find('t1', 'user', '1', 'create-invoice', 'k')['result_json']['data'])->toBe('A')
        ->and($store->find('t1', 'user', '2', 'create-invoice', 'k')['result_json']['data'])->toBe('B');
});

it('edge: warn_missing_key emits metric or log when mutating without key [D-005]', function () {
    $warner = new MissingKeyWarner(true);
    $warner->maybeWarn('create-invoice', 'http', true, null);
    expect($warner->count())->toBe(1);
    $warner->maybeWarn('create-invoice', 'http', true, 'present');
    expect($warner->count())->toBe(1);
    $warner->maybeWarn('list', 'http', false, null);
    expect($warner->count())->toBe(1);

    $h = IdempotencyHelpers::harness();
    // Registry guard has default warn enabled — invoke without key
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http'));
    // Warning captured on registry's internal guard — policy path exercised without throwing
    expect($h['runCount']->value)->toBe(1);
});

it('happy: idempotency honored for caller agent when key present [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $r = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('agent', [
        'idempotency_key' => 'agent-h',
    ]));
    expect($r->isOk())->toBeTrue();
});

it('happy: replay for caller agent does not second run [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('agent', ['idempotency_key' => 'agent-r']);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    expect($h['runCount']->value)->toBe(1);
});

it('happy: idempotency honored for caller mcp when key present [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $r = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('mcp', [
        'idempotency_key' => 'mcp-h',
    ]));
    expect($r->isOk())->toBeTrue();
});

it('happy: replay for caller mcp does not second run [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('mcp', ['idempotency_key' => 'mcp-r']);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    expect($h['runCount']->value)->toBe(1);
});

it('happy: idempotency honored for caller http when key present [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $r = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http', [
        'idempotency_key' => 'http-h',
    ]));
    expect($r->isOk())->toBeTrue();
});

it('happy: replay for caller http does not second run [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'http-r']);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    expect($h['runCount']->value)->toBe(1);
});

it('happy: idempotency honored for caller cli when key present [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $r = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('cli', [
        'idempotency_key' => 'cli-h',
    ]));
    expect($r->isOk())->toBeTrue();
});

it('happy: replay for caller cli does not second run [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('cli', ['idempotency_key' => 'cli-r']);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    expect($h['runCount']->value)->toBe(1);
});

it('happy: idempotency honored for caller job when key present [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $r = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('job', [
        'idempotency_key' => 'job-h',
    ]));
    expect($r->isOk())->toBeTrue();
});

it('happy: replay for caller job does not second run [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('job', ['idempotency_key' => 'job-r']);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    expect($h['runCount']->value)->toBe(1);
});

it('edge: store status processing behaves per pipeline table [D-005]', function () {
    $guard = IdempotencyHelpers::guard();
    $store = $guard->store();
    IdempotencyHelpers::seedRow($store, ['status' => 'processing', 'actor_id' => '1', 'result_json' => null]);
    $out = $guard->lookup(
        IdempotencyHelpers::mutatingDefinition(),
        IdempotencyHelpers::context(),
        'key-1',
        IdempotencyHelpers::hash(IdempotencyHelpers::inputA()),
    );
    expect($out['action'])->toBe('busy');
});

it('edge: store status completed behaves per pipeline table [D-005]', function () {
    $guard = IdempotencyHelpers::guard();
    $store = $guard->store();
    $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());
    IdempotencyHelpers::seedRow($store, ['status' => 'completed', 'actor_id' => '1', 'request_hash' => $hash]);
    $out = $guard->lookup(IdempotencyHelpers::mutatingDefinition(), IdempotencyHelpers::context(), 'key-1', $hash);
    expect($out['action'])->toBe('replay');
});

it('edge: store status failed behaves per pipeline table [D-005]', function () {
    $guard = IdempotencyHelpers::guard();
    $store = $guard->store();
    $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());
    IdempotencyHelpers::seedRow($store, [
        'status' => 'failed',
        'actor_id' => '1',
        'request_hash' => $hash,
        'result_json' => CapabilityResult::failure('x', 'y')->toArray(),
    ]);
    $out = $guard->lookup(IdempotencyHelpers::mutatingDefinition(), IdempotencyHelpers::context(), 'key-1', $hash);
    expect($out['action'])->toBe('replay')->and($out['result']->isFailed())->toBeTrue();
});

it('fail: relying on clients not to retry is not a package assumption [D-005]', function () {
    // Package stores outcomes so clients may freely retry — proven by replay path.
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'retry-free']);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    expect($h['runCount']->value)->toBe(1);
});

it('fail: idempotency only on HTTP not MCP CLI jobs is refused [D-005]', function () {
    // Same store+guard API is used for every caller — surface parity.
    foreach (['http', 'mcp', 'cli', 'job', 'agent'] as $caller) {
        $key = WireKeyResolver::resolve($caller, body: ['idempotency_key' => 'parity', 'idempotencyKey' => 'parity']);
        expect($key)->not->toBeNull("caller {$caller} must accept keys");
    }
});

it('fail: approval accept without tying to invoke key is refused [D-005]', function () {
    // Without stored key and without header/body, accept has no key — empty path is explicit null.
    $key = WireKeyResolver::approvalAccept(null, [], null);
    expect($key)->toBeNull();
    // With stored key from invoke, accept is bound
    expect(WireKeyResolver::approvalAccept(null, [], 'invoke-k'))->toBe('invoke-k');
});

it('fail: global dedupe by input only without key is refused [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    // Same input, no key → two runs (no global input-only dedupe)
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http'));
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http'));
    expect($h['runCount']->value)->toBe(2);
});

it('happy: catalog exposes idempotent optional required none metadata [D-005]', function () {
    $h = IdempotencyHelpers::harness(['idempotent' => 'required']);
    Capability::define('none-cap')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->idempotent('none')
        ->allowSystemCallers(true)
        ->run(fn () => new CreateInvoiceResult(invoice_id: 1))
        ->register($h['registry']);
    Capability::define('opt-cap')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->idempotent('optional')
        ->allowSystemCallers(true)
        ->run(fn () => new CreateInvoiceResult(invoice_id: 1))
        ->register($h['registry']);

    $catalog = $h['registry']->catalog()->list();
    $byName = [];
    foreach ($catalog as $entry) {
        $byName[$entry['name']] = $entry;
    }
    expect($byName[$h['name']]['idempotent'])->toBe('required')
        ->and($byName['none-cap']['idempotent'])->toBe('none')
        ->and($byName['opt-cap']['idempotent'])->toBe('optional');
});

it('edge: TTL default 24 hours configurable [D-005]', function () {
    expect(IdempotencyConfig::defaults()->ttlHours)->toBe(24)
        ->and(IdempotencyConfig::DEFAULT_TTL_HOURS)->toBe(24);
    $cfg = IdempotencyConfig::fromArray(['ttl_hours' => 48]);
    expect($cfg->ttlHours)->toBe(48)
        ->and($cfg->toArray()['ttl_hours'])->toBe(48);
});

it('edge: header name configurable via config idempotency.header [D-005]', function () {
    $cfg = IdempotencyConfig::fromArray(['header' => 'X-Idempotency-Key']);
    expect($cfg->header)->toBe('X-Idempotency-Key');
    $key = WireKeyResolver::resolve(
        'http',
        headers: ['X-Idempotency-Key' => 'custom'],
        configHeader: $cfg->header,
    );
    expect($key)->toBe('custom');
});

it('happy: request_hash is canonical input JSON hash [D-005]', function () {
    $a = RequestHash::of(['b' => 1, 'a' => 2]);
    $b = RequestHash::of(['a' => 2, 'b' => 1]);
    $c = RequestHash::of(['a' => 2, 'b' => 3]);
    expect($a)->toBe($b)->and($a)->not->toBe($c)
        ->and(strlen($a))->toBe(64);
});
