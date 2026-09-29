<?php

// REQ-012: Artisan flag matrix for mutating capability:run (D-002). Unit-only.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Artisan\ArtisanCapabilityInvoker;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\InvalidArtisanFlagsException;
use Rawphp\Capabilities\Support\MissingArtisanActorException;
use Rawphp\Capabilities\Support\MissingJobTenantException;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('edge: artisan mutate path when --acting-as=1 [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
    $invoker = new ArtisanCapabilityInvoker($h['registry']);
    $parsed = ArtisanCapabilityInvoker::parseFlags(['acting-as' => '1']);

    expect($parsed['acting_as'])->toBe(1)
        ->and($parsed['system'])->toBeNull();

    $result = $invoker->run([
        'name' => $h['name'],
        'input' => H::homeInput(),
        'acting_as' => $parsed['acting_as'],
        'tenant' => 'tenant-a',
        'mutating' => true,
    ]);
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->context?->caller())->toBe('artisan');
});

it('edge: artisan mutate path when --system=scheduler [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler']]);
    $invoker = new ArtisanCapabilityInvoker($h['registry']);
    $parsed = ArtisanCapabilityInvoker::parseFlags(['system' => 'scheduler']);

    expect($parsed['system'])->toBe('scheduler')
        ->and($parsed['acting_as'])->toBeNull();

    $result = $invoker->run([
        'name' => $h['name'],
        'input' => H::homeInput(),
        'system' => $parsed['system'],
        'tenant' => 'tenant-a',
        'mutating' => true,
    ]);
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->context?->actor())->toBeInstanceOf(SystemActor::class);
});

it('edge: artisan mutate path when --system=scheduler --tenant=t1 [D-002]', function () {
    // Seed home tenant as t1 so scoped authorize matches --tenant=t1.
    $h = H::scopeHarness([
        'allowSystemCallers' => ['scheduler'],
        'tenancy_required' => true,
        'tenant_id' => 't1',
    ]);
    $invoker = new ArtisanCapabilityInvoker($h['registry']);
    $parsed = ArtisanCapabilityInvoker::parseFlags([
        'system' => 'scheduler',
        'tenant' => 't1',
    ]);

    expect($parsed['system'])->toBe('scheduler')
        ->and($parsed['tenant'])->toBe('t1');

    $result = $invoker->run([
        'name' => $h['name'],
        'input' => H::homeInput(),
        'system' => $parsed['system'],
        'tenant' => $parsed['tenant'],
        'mutating' => true,
        'tenancy_required' => true,
    ]);
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastScopeTenant())->toBe('t1');
});

it('fail: artisan mutate refused or invalid when no-flags [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
    $invoker = new ArtisanCapabilityInvoker($h['registry']);
    $parsed = ArtisanCapabilityInvoker::parseFlags([]);

    expect($parsed['acting_as'])->toBeNull()
        ->and($parsed['system'])->toBeNull();

    expect(fn () => $invoker->run([
        'name' => $h['name'],
        'input' => H::homeInput(),
        'mutating' => true,
    ]))->toThrow(MissingArtisanActorException::class);

    expect($h['runCount']->value)->toBe(0);
});

it('fail: artisan mutate refused or invalid when --acting-as=1 --system=scheduler [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
    $invoker = new ArtisanCapabilityInvoker($h['registry']);

    expect(fn () => ArtisanCapabilityInvoker::parseFlags([
        'acting-as' => '1',
        'system' => 'scheduler',
    ]))->toThrow(InvalidArtisanFlagsException::class);

    expect(fn () => $invoker->run([
        'name' => $h['name'],
        'input' => H::homeInput(),
        'acting_as' => 1,
        'system' => 'scheduler',
        'mutating' => true,
    ]))->toThrow(InvalidArtisanFlagsException::class);

    expect($h['runCount']->value)->toBe(0);
});

function artisanInvokerRegistry(): CapabilityRegistry
{
    $reg = (new CapabilityRegistry)->withAuthorizer(StubAuthorizer::allow());
    $reg->register(new CapabilityDefinition(
        name: 'art.cap',
        description: 'd',
        readOnly: false,
        input: CreateInvoiceInput::class,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok(['ok' => true]),
    ));
    $reg->register(new CapabilityDefinition(
        name: 'art.deny-sys',
        description: 'd',
        readOnly: true,
        allowSystemCallers: false,
        run: static fn () => CapabilityResult::ok([]),
    ));

    return $reg;
}

function artisanInvoiceInput(): array
{
    return ['customer_id' => 1, 'amount_cents' => 1, 'currency' => 'USD'];
}

it('fail: artisan mutating run without actor or system throws MissingArtisanActorException', function () {
    $inv = new ArtisanCapabilityInvoker(artisanInvokerRegistry());

    expect(fn () => $inv->run([
        'name' => 'art.cap',
        'input' => artisanInvoiceInput(),
    ]))->toThrow(MissingArtisanActorException::class);
});

it('fail: artisan system run is forbidden for a capability that denies system callers', function () {
    $inv = new ArtisanCapabilityInvoker(artisanInvokerRegistry());

    $sysDenied = $inv->run([
        'name' => 'art.deny-sys',
        'system' => 'billing',
        'tenant' => 't1',
        'mutating' => false,
    ]);

    expect($sysDenied->errorCode())->toBe('forbidden');
});

it('fail: artisan system run with tenancy_required and no tenant throws MissingJobTenantException', function () {
    $inv = new ArtisanCapabilityInvoker(artisanInvokerRegistry());

    expect(fn () => $inv->run([
        'name' => 'art.cap',
        'input' => artisanInvoiceInput(),
        'system' => 'billing',
        'tenancy_required' => true,
    ]))->toThrow(MissingJobTenantException::class);
});

it('happy: artisan run resolves acting_as through user_resolver', function () {
    $inv = new ArtisanCapabilityInvoker(artisanInvokerRegistry());

    $withUser = $inv->run([
        'name' => 'art.cap',
        'input' => artisanInvoiceInput(),
        'acting_as' => 42,
        'tenant' => 't1',
        'user_resolver' => static fn ($id) => (object) ['id' => $id],
        'skip_server_rules' => true,
    ]);

    expect($withUser)->toBeInstanceOf(CapabilityResult::class);
});

it('fail: artisan run throws RuntimeException when user_resolver finds no user', function () {
    $inv = new ArtisanCapabilityInvoker(artisanInvokerRegistry());

    expect(fn () => $inv->run([
        'name' => 'art.cap',
        'input' => artisanInvoiceInput(),
        'acting_as' => 99,
        'user_resolver' => static fn () => null,
    ]))->toThrow(RuntimeException::class);
});

it('happy: artisan run accepts a numeric-string acting_as without a resolver', function () {
    $inv = new ArtisanCapabilityInvoker(artisanInvokerRegistry());

    $numeric = $inv->run([
        'name' => 'art.cap',
        'input' => artisanInvoiceInput(),
        'acting_as' => '7',
        'tenant' => 't1',
        'skip_server_rules' => true,
    ]);

    expect($numeric)->toBeInstanceOf(CapabilityResult::class);
});

it('happy: artisan run executes as a named system actor with a tenant', function () {
    $inv = new ArtisanCapabilityInvoker(artisanInvokerRegistry());

    $sysOk = $inv->run([
        'name' => 'art.cap',
        'input' => artisanInvoiceInput(),
        'system' => 'ops',
        'tenant' => 't1',
        'skip_server_rules' => true,
    ]);

    expect($sysOk)->toBeInstanceOf(CapabilityResult::class);
});

it('edge: artisan invoker statics report non-product CLI, a caller string and parsed acting_as', function () {
    expect(ArtisanCapabilityInvoker::isProductCli())->toBeFalse()
        ->and(ArtisanCapabilityInvoker::caller())->toBeString()
        ->and(ArtisanCapabilityInvoker::parseFlags(['acting-as' => '3', 'tenant' => 't']))->toHaveKey('acting_as');
});
