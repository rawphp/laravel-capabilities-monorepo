<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\Identity\ModelUserFactory;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\InMemoryUserModel;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * Governance runs on the actor (D-002/D-003): chat identities resolve to the host user model,
 * the same rule the AI sibling applies, and fail closed when the id does not resolve.
 */
final class NotQueryableUser {}

beforeEach(function () {
    InMemoryUserModel::seed(['u1']);
});

it('happy: provider-bound linker resolves a linked chat user to the configured user_model [D-002]', function () {
    $app = H::container(['user_model' => InMemoryUserModel::class]);
    $linker = $app->make(IdentityLinker::class);
    $linker->link('42', 'u1');

    $user = $linker->resolve(['telegram_user_id' => '42']);

    expect($user)->toBe(InMemoryUserModel::$rows['u1']);
});

it('edge: user_model falls back to auth.providers.users.model [D-002]', function () {
    $app = H::container([], ['auth.providers.users.model' => InMemoryUserModel::class]);
    $linker = $app->make(IdentityLinker::class);
    $linker->link('42', 'u1');

    expect($linker->resolve(['telegram_user_id' => '42']))->toBeInstanceOf(InMemoryUserModel::class);
});

it('fail: a link whose user id no longer resolves fails closed [D-002]', function () {
    $app = H::container(['user_model' => InMemoryUserModel::class]);
    $linker = $app->make(IdentityLinker::class);
    $linker->link('42', 'u1');
    InMemoryUserModel::$rows = []; // user deleted after linking

    expect(fn () => $linker->resolve(['telegram_user_id' => '42']))
        ->toThrow(RuntimeException::class, 'does not resolve');
});

it('fail: no user model configured fails closed on first resolve, not at boot [D-021]', function () {
    $app = H::container(['identity' => [
        'mode' => 'allowlist',
        'allowlist' => [['telegram_user_id' => '42', 'laravel_user_id' => 'u1']],
    ]]);
    $linker = $app->make(IdentityLinker::class);

    expect($linker->resolve(['telegram_user_id' => 'unlinked']))->toBeNull()
        ->and(fn () => $linker->resolve(['telegram_user_id' => '42']))
        ->toThrow(RuntimeException::class, 'No user model configured');
});

it('fail: unknown or non-queryable user model classes are refused [D-002]', function (string $class, string $message) {
    expect(fn () => (new ModelUserFactory($class))('u1', null))->toThrow(RuntimeException::class, $message);
})->with([
    'missing class' => ['App\\Models\\DoesNotExist', 'does not exist'],
    'no query()' => [NotQueryableUser::class, 'not queryable'],
]);

it('happy: user_model is a documented config key [MSG-001]', function () {
    expect(H::config()->hasKey('user_model'))->toBeTrue();
});
