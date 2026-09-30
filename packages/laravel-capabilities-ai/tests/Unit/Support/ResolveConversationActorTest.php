<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Rawphp\CapabilitiesAi\Support\EloquentActorLookup;
use Rawphp\CapabilitiesAi\Support\ResolveConversationActor;
use Rawphp\CapabilitiesAi\Support\UnresolvedConversationActorException;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryActorLookup;

/**
 * Queryable user model stub: records find() keys, holds users by int key only (no database).
 */
final class ActorLookupIntKeyUser
{
    /** @var list<mixed> */
    public static array $finds = [];

    /** @var array<int, object> */
    public static array $rows = [];

    public static function query(): object
    {
        return new class
        {
            public function find(mixed $id): ?object
            {
                ActorLookupIntKeyUser::$finds[] = $id;

                return is_int($id) ? (ActorLookupIntKeyUser::$rows[$id] ?? null) : null;
            }
        };
    }
}

final class ResolveActorTestUser extends Model {}

it('builds bus invoke options with the server-chosen caller and the actor', function () {
    $actor = new stdClass;
    $actors = new ResolveConversationActor;

    expect($actors->invokeOptions($actor, ResolveConversationActor::CALLER_AGENT))
        ->toBe(['caller' => 'agent', 'actor' => $actor])
        ->and($actors->invokeOptions($actor, ResolveConversationActor::CALLER_JOB, ['idempotency_key' => 'k']))
        ->toBe(['caller' => 'job', 'actor' => $actor, 'idempotency_key' => 'k']);
});

it('never lets extra options override the caller or actor', function () {
    $actor = new stdClass;

    $options = (new ResolveConversationActor)->invokeOptions($actor, ResolveConversationActor::CALLER_AGENT, [
        'caller' => 'http',
        'actor' => new stdClass,
    ]);

    expect($options['caller'])->toBe('agent')
        ->and($options['actor'])->toBe($actor);
});

it('resolves the conversation user through the lookup', function () {
    $users = new InMemoryActorLookup;
    $user = $users->add(new ResolveActorTestUser);

    expect((new ResolveConversationActor(lookup: $users))->resolve(' '.$user->id.' '))->toBe($user)
        ->and((new ResolveConversationActor(lookup: $users))->resolve($user->id))->toBe($user);
});

it('fails closed on a missing, blank, non-scalar or unknown user_id', function (mixed $userId, string $message) {
    expect(fn () => (new ResolveConversationActor(lookup: new InMemoryActorLookup))->resolve($userId))
        ->toThrow(UnresolvedConversationActorException::class, $message);
})->with([
    'null' => [null, 'user_id is required'],
    'blank' => ['  ', 'user_id is required'],
    'array' => [['1'], 'user_id is required'],
    'unknown' => ['42', 'does not resolve to a user'],
]);

it('eloquent lookup retries a digit key as an int and returns null when nothing matches', function () {
    ActorLookupIntKeyUser::$finds = [];
    ActorLookupIntKeyUser::$rows = [7 => $user = new stdClass];
    $lookup = new EloquentActorLookup(ActorLookupIntKeyUser::class);

    expect($lookup->find('7'))->toBe($user)
        ->and($lookup->find('8'))->toBeNull()
        ->and($lookup->find('abc'))->toBeNull()
        ->and(ActorLookupIntKeyUser::$finds)->toBe(['7', 7, '8', 8, 'abc']);
});
