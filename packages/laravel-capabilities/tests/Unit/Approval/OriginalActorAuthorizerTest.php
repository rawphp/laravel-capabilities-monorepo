<?php

// Re-validation on accept, step 4: authorize() for the ORIGINAL actor under current scope.
// OriginalActorAuthorizer rehydrates the requester from the approval row and re-runs the
// registry's authorize decision. Unit-only: in-memory registry, closure actor resolver.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\OriginalActorAuthorizer;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function oaaRow(array $overrides = []): array
{
    return array_merge([
        'id' => 'apr_1',
        'capability_name' => 'create-invoice',
        'tenant_id' => 't1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
        'original_caller' => 'http',
        'input_json' => ['customer_id' => 1, 'amount_cents' => 500, 'currency' => 'AUD'],
    ], $overrides);
}

function oaaUser(int|string $id): object
{
    $user = new stdClass;
    $user->id = $id;

    return $user;
}

it('happy: allows when the rehydrated original actor is still authorized', function () {
    $seen = [];
    $registry = new CapabilityRegistry;
    $registry->define('create-invoice')
        ->input(CreateInvoiceInput::class)
        ->authorize(function (mixed $input, CapabilityContext $ctx) use (&$seen): bool {
            $seen = ['input' => $input::class, 'actor' => $ctx->actor()->id, 'tenant' => $ctx->tenantId(), 'caller' => $ctx->caller()];

            return true;
        })
        ->run(static fn () => 'never')
        ->register($registry);

    $authorizer = new OriginalActorAuthorizer($registry, static fn (string $type, string $id) => oaaUser($id));

    expect($authorizer(oaaRow()))->toBeTrue()
        ->and($seen)->toBe(['input' => CreateInvoiceInput::class, 'actor' => '7', 'tenant' => 't1', 'caller' => 'http']);
});

it('fail: denies when the original actor lost permission since request', function () {
    $registry = new CapabilityRegistry;
    $registry->define('create-invoice')
        ->input(CreateInvoiceInput::class)
        ->authorize(static fn (mixed $input, CapabilityContext $ctx): bool => $ctx->actor()->id !== '7')
        ->run(static fn () => 'never')
        ->register($registry);

    $authorizer = new OriginalActorAuthorizer($registry, static fn (string $type, string $id) => oaaUser($id));

    expect($authorizer(oaaRow()))->toBeFalse();
});

it('fail: denies when the requester can no longer be resolved', function () {
    $registry = new CapabilityRegistry;
    $registry->define('create-invoice')
        ->input(CreateInvoiceInput::class)
        ->authorize(static fn (): bool => true)
        ->run(static fn () => 'never')
        ->register($registry);

    $authorizer = new OriginalActorAuthorizer($registry, static fn () => null);

    expect($authorizer(oaaRow()))->toBeFalse();
});

it('fail: denies when the capability is no longer registered', function () {
    $authorizer = new OriginalActorAuthorizer(new CapabilityRegistry, static fn (string $type, string $id) => oaaUser($id));

    expect($authorizer(oaaRow(['capability_name' => 'gone'])))->toBeFalse();
});

it('fail: falls back to the registry authorizer, which denies by default', function () {
    $registry = new CapabilityRegistry;
    $registry->define('create-invoice')->input(CreateInvoiceInput::class)->run(static fn () => 'never')->register($registry);

    $authorizer = new OriginalActorAuthorizer($registry, static fn (string $type, string $id) => oaaUser($id));

    expect($authorizer(oaaRow()))->toBeFalse();

    $registry->withAuthorizer(StubAuthorizer::allow());
    expect($authorizer(oaaRow()))->toBeTrue();
});

it('edge: rehydrates a system requester as SystemActor without calling the resolver', function () {
    $actor = null;
    $registry = new CapabilityRegistry;
    $registry->define('create-invoice')
        ->input(CreateInvoiceInput::class)
        ->authorize(function (mixed $input, CapabilityContext $ctx) use (&$actor): bool {
            $actor = $ctx->actor();

            return true;
        })
        ->run(static fn () => 'never')
        ->register($registry);

    $authorizer = new OriginalActorAuthorizer($registry, static fn () => throw new RuntimeException('resolver must not run'));

    expect($authorizer(oaaRow(['requester_actor_type' => 'system', 'requester_actor_id' => 'nightly-billing', 'original_caller' => 'job'])))->toBeTrue()
        ->and($actor)->toBeInstanceOf(SystemActor::class)
        ->and($actor->name)->toBe('nightly-billing');
});

it('edge: denies a row with no requester id or an unknown caller', function () {
    $registry = new CapabilityRegistry;
    $registry->define('create-invoice')
        ->input(CreateInvoiceInput::class)
        ->authorize(static fn (): bool => true)
        ->run(static fn () => 'never')
        ->register($registry);

    $authorizer = new OriginalActorAuthorizer($registry, static fn (string $type, string $id) => oaaUser($id));

    expect($authorizer(oaaRow(['requester_actor_id' => ''])))->toBeFalse()
        ->and($authorizer(oaaRow(['requester_actor_type' => 'system', 'requester_actor_id' => ''])))->toBeFalse()
        ->and($authorizer(oaaRow(['original_caller' => 'telepathy'])))->toBeFalse();
});

it('edge: hydrates stored input into the capability DTO before authorize', function () {
    $input = null;
    $registry = new CapabilityRegistry;
    $registry->define('create-invoice')
        ->input(CreateInvoiceInput::class)
        ->authorize(function (mixed $in) use (&$input): bool {
            $input = $in;

            return true;
        })
        ->run(static fn () => 'never')
        ->register($registry);

    $authorizer = new OriginalActorAuthorizer($registry, static fn (string $type, string $id) => oaaUser($id));
    $row = oaaRow([
        'capability_name' => 'create-invoice',
        'input_json' => ['customer_id' => 1, 'amount_cents' => 500, 'currency' => 'AUD'],
    ]);

    expect($authorizer($row))->toBeTrue()
        ->and($input)->toBeInstanceOf(CreateInvoiceInput::class)
        ->and($input->amount_cents)->toBe(500);

    expect($authorizer(array_merge($row, ['input_json' => ['customer_id' => 'nope']])))->toBeFalse();
});

it('edge: a null tenant yields a context without scope', function () {
    $tenant = 'unset';
    $registry = new CapabilityRegistry;
    $registry->define('create-invoice')
        ->input(CreateInvoiceInput::class)
        ->authorize(function (mixed $input, CapabilityContext $ctx) use (&$tenant): bool {
            $tenant = $ctx->scope();

            return true;
        })
        ->run(static fn () => 'never')
        ->register($registry);

    $authorizer = new OriginalActorAuthorizer($registry, static fn (string $type, string $id) => oaaUser($id));

    expect($authorizer(oaaRow(['tenant_id' => null])))->toBeTrue()
        ->and($tenant)->toBeNull();
});

it('fail: denies when the stored input is not an object payload', function () {
    $registry = new CapabilityRegistry;
    $registry->define('create-invoice')
        ->input(CreateInvoiceInput::class)
        ->authorize(static fn (): bool => true)
        ->run(static fn () => 'never')
        ->register($registry);

    $authorizer = new OriginalActorAuthorizer($registry, static fn (string $type, string $id) => oaaUser($id));

    expect($authorizer(oaaRow(['input_json' => 'redacted'])))->toBeFalse();
});
