<?php

// IdempotencyGuard: key policy, lookup state machine (continue/busy/replay/conflict), storeResult, expiry.

declare(strict_types=1);

use Rawphp\Capabilities\Idempotency\IdempotencyConfig;
use Rawphp\Capabilities\Idempotency\IdempotencyKey;
use Rawphp\Capabilities\Idempotency\MissingKeyWarner;
use Rawphp\Capabilities\Pipeline\IdempotencyGuard;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;

/**
 * @return array{guard: IdempotencyGuard, store: InMemoryIdempotencyStore, ctx: CapabilityContext, optional: CapabilityDefinition, hash: string}
 */
function igGuardHarness(): array
{
    $clock = new FixedClock(new DateTimeImmutable('2026-03-01T00:00:00Z'));
    $store = new InMemoryIdempotencyStore($clock);
    $guard = new IdempotencyGuard($store, $clock, IdempotencyConfig::defaults(), new MissingKeyWarner(true));

    $optional = new CapabilityDefinition(
        name: 'opt',
        description: 'd',
        input: CreateInvoiceInput::class,
        readOnly: false,
        idempotent: CapabilityDefinition::IDEMPOTENT_OPTIONAL,
        run: static fn () => CapabilityResult::ok([]),
    );
    $ctx = CapabilityContext::make([
        'caller' => 'http',
        'actor' => SystemActor::named('actor-1'),
        'scope' => new CapabilityScope(tenantId: 'ten-1'),
    ]);

    return ['guard' => $guard, 'store' => $store, 'ctx' => $ctx, 'optional' => $optional, 'hash' => $guard->hashInput(['x' => 1])];
}

it('happy: guard exposes its config, warner and store and hashes input to a string', function () {
    $h = igGuardHarness();

    expect($h['guard']->config())->toBeInstanceOf(IdempotencyConfig::class)
        ->and($h['guard']->warner())->toBeInstanceOf(MissingKeyWarner::class)
        ->and($h['guard']->store())->toBe($h['store'])
        ->and($h['guard']->hashInput(['a' => 1]))->toBeString();
});

it('fail: key policy requires a valid key for required capabilities and lets none/optional pass', function () {
    $h = igGuardHarness();
    $guard = $h['guard'];

    $none = new CapabilityDefinition(name: 'ro', description: 'd', readOnly: true, idempotent: CapabilityDefinition::IDEMPOTENT_NONE);
    expect($guard->assertKeyPolicy($none, null))->toBeNull();

    $required = new CapabilityDefinition(
        name: 'req',
        description: 'd',
        input: CreateInvoiceInput::class,
        readOnly: false,
        idempotent: CapabilityDefinition::IDEMPOTENT_REQUIRED,
        run: static fn () => CapabilityResult::ok([]),
    );
    expect($guard->assertKeyPolicy($required, null)?->errorCode())->toBe('validation_failed');
    expect($guard->assertKeyPolicy($required, '!!!bad!!!')?->errorCode())->toBe('validation_failed');

    // missing key warns but continues
    expect($guard->assertKeyPolicy($h['optional'], null, 'http'))->toBeNull();

    expect(IdempotencyKey::isValid(str_repeat('a', 16)))->toBeTrue();
});

it('happy: lookup walks continue, busy, replay and conflict as a request is stored and repeated', function () {
    $h = igGuardHarness();
    ['guard' => $guard, 'ctx' => $ctx, 'optional' => $optional, 'hash' => $hash] = $h;
    $validKey = str_repeat('a', 16);

    // first lookup inserts processing
    expect($guard->lookup($optional, $ctx, $validKey, $hash)['action'])->toBe('continue');

    // busy while processing
    expect($guard->lookup($optional, $ctx, $validKey, $hash)['action'])->toBe('busy');

    // complete then replay
    $guard->storeResult($optional, $ctx, $validKey, $hash, CapabilityResult::ok(['done' => true]));
    $replay = $guard->lookup($optional, $ctx, $validKey, $hash);
    expect($replay['action'])->toBe('replay')->and($replay['result']->isOk())->toBeTrue();

    // conflict different hash
    expect($guard->lookup($optional, $ctx, $validKey, $guard->hashInput(['x' => 2]))['action'])->toBe('conflict');
});

it('edge: lookup replays a stored failure and reports conflict for an invalid key', function () {
    $h = igGuardHarness();
    ['guard' => $guard, 'ctx' => $ctx, 'optional' => $optional, 'hash' => $hash] = $h;

    $key2 = str_repeat('b', 16);
    $guard->lookup($optional, $ctx, $key2, $hash);
    $guard->storeResult($optional, $ctx, $key2, $hash, CapabilityResult::failure('domain_error', 'boom'));
    $failReplay = $guard->lookup($optional, $ctx, $key2, $hash);
    expect($failReplay['action'])->toBe('replay')->and($failReplay['result']->isOk())->toBeFalse();

    // invalid key in lookup
    expect($guard->lookup($optional, $ctx, '!!', $hash)['action'])->toBe('conflict');
});

it('edge: a pending_approval row conflicts on a different hash and replays the same approval on the same hash', function () {
    $h = igGuardHarness();
    ['guard' => $guard, 'ctx' => $ctx, 'optional' => $optional, 'hash' => $hash] = $h;

    $key3 = str_repeat('c', 16);
    $guard->lookup($optional, $ctx, $key3, $hash);
    $guard->storeResult($optional, $ctx, $key3, $hash, CapabilityResult::approvalRequired('ap-1', 'need approval'));

    $same = $guard->lookup($optional, $ctx, $key3, $hash);

    expect($guard->lookup($optional, $ctx, $key3, $guard->hashInput(['z' => 9]))['action'])->toBe('conflict')
        ->and($same['action'])->toBe('replay')
        ->and($same['result']->approvalId())->toBe('ap-1')
        // The accepted execution of that approval is the one caller allowed through (L-102).
        ->and($guard->lookup($optional, $ctx, $key3, $hash, executingApprovalId: 'ap-1')['action'])->toBe('continue');
});

it('edge: storeResult is a no-op for an invalid key or a none-policy capability', function () {
    $h = igGuardHarness();
    ['guard' => $guard, 'ctx' => $ctx, 'optional' => $optional, 'hash' => $hash] = $h;
    $none = new CapabilityDefinition(name: 'ro', description: 'd', readOnly: true, idempotent: CapabilityDefinition::IDEMPOTENT_NONE);

    $guard->storeResult($optional, $ctx, '!!', $hash, CapabilityResult::ok([]));
    $guard->storeResult($none, $ctx, str_repeat('a', 16), $hash, CapabilityResult::ok([]));

    expect($guard->lookup($optional, $ctx, str_repeat('a', 16), $hash)['action'])->toBe('continue');
});

it('edge: isExpired treats blank and unparsable expiry as live and compares real dates', function () {
    $guard = igGuardHarness()['guard'];

    expect($guard->isExpired(['expires_at' => '']))->toBeFalse()
        ->and($guard->isExpired(['expires_at' => 'not-a-date']))->toBeFalse()
        ->and($guard->isExpired(['expires_at' => '2020-01-01T00:00:00Z']))->toBeTrue()
        ->and($guard->isExpired(['expires_at' => '2099-01-01T00:00:00Z']))->toBeFalse();
});

it('edge: an expired stored row is treated as missing and a non-envelope result replays as ok', function () {
    $h = igGuardHarness();
    ['guard' => $guard, 'store' => $store, 'ctx' => $ctx, 'optional' => $optional, 'hash' => $hash] = $h;
    $row = [
        'tenant_id' => 'ten-1',
        'actor_type' => 'system',
        'actor_id' => 'actor-1',
        'capability_name' => 'opt',
        'request_hash' => $hash,
        'status' => 'completed',
    ];

    // expired existing treated as missing
    $key4 = str_repeat('d', 16);
    $store->put($row + [
        'idempotency_key' => $key4,
        'result_json' => CapabilityResult::ok(['old' => true])->toArray(),
        'expires_at' => '2020-01-01T00:00:00Z',
    ]);
    expect($guard->lookup($optional, $ctx, $key4, $hash)['action'])->toBe('continue');

    // hydrateResult via completed with non-envelope stored
    $key5 = str_repeat('e', 16);
    $guard->lookup($optional, $ctx, $key5, $hash);
    $store->put($row + [
        'idempotency_key' => $key5,
        'result_json' => ['raw' => true],
        'expires_at' => '2099-01-01T00:00:00Z',
    ]);
    $rawReplay = $guard->lookup($optional, $ctx, $key5, $hash);
    expect($rawReplay['action'])->toBe('replay')->and($rawReplay['result']->isOk())->toBeTrue();
});
