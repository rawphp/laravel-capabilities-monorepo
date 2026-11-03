<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\CapabilitiesAi\Domain\ClientMessageConflictException;
use Rawphp\CapabilitiesAi\Domain\ClientMessageMismatchException;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryConversationStore;

/**
 * @return object{service: ConversationService, store: InMemoryConversationStore, progress: ArrayProgressStore, limiter: InMemoryRateLimiter, jobs: ArrayObject<int, object>}
 */
function clientKeyWorld(int $maxConcurrentTurns = 0, int $turnsPerMinute = 0): object
{
    $jobs = new ArrayObject;
    $store = new InMemoryConversationStore;
    $progress = new ArrayProgressStore;
    $limiter = new InMemoryRateLimiter;
    $service = new ConversationService(
        static function (object $job) use ($jobs): void {
            $jobs[] = $job;
        },
        $progress,
        maxConcurrentTurns: $maxConcurrentTurns,
        turnLimiter: $limiter,
        turnsPerMinute: $turnsPerMinute,
        store: $store,
    );

    return (object) ['service' => $service, 'store' => $store, 'progress' => $progress, 'limiter' => $limiter, 'jobs' => $jobs];
}

it('replays nothing for a key that was never stored', function () {
    $world = clientKeyWorld();

    expect($world->service->replayClientMessage('u1', 'k1', 'hi'))->toBeNull();
});

it('replays a stored key with the same content as the first send\'s ids, creating nothing', function () {
    $world = clientKeyWorld(maxConcurrentTurns: 1, turnsPerMinute: 1);
    $ids = $world->service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1');

    $replayed = $world->service->replayClientMessage('u1', 'k1', 'hi');

    expect($replayed)->toBe($ids)
        ->and($world->store->conversations)->toHaveCount(1)
        ->and($world->store->messages)->toHaveCount(1)
        ->and($world->store->turns)->toHaveCount(1)
        ->and($world->jobs)->toHaveCount(1)
        ->and($world->progress->since($ids['turn_ulid'], 0))->toHaveCount(1)
        // Neither limit is consulted: one turn is queued and the per-minute budget of 1 is spent.
        ->and($world->limiter->attemptCount('rl:ai:user:u1'))->toBe(1);
});

it('replays a first message sent without conversation_ulid into the conversation it created', function () {
    $world = clientKeyWorld();
    $ids = $world->service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1');

    expect($world->service->replayClientMessage('u1', 'k1', 'hi')['conversation_ulid'] ?? null)
        ->toBe($ids['conversation_ulid']);
});

it('throws a mismatch when a stored key is replayed with different content', function () {
    $world = clientKeyWorld();
    $world->service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1');

    expect(fn () => $world->service->replayClientMessage('u1', 'k1', 'hello'))
        ->toThrow(ClientMessageMismatchException::class);
});

it('scopes the key to the conversation owner, across all their conversations', function () {
    $world = clientKeyWorld();
    $first = $world->service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1');

    $otherOwner = $world->service->createUserMessage('hi', userId: 'u2', clientMessageId: 'k1');

    expect($world->service->replayClientMessage('u2', 'k1', 'hi'))->toBe($otherOwner)
        ->and($world->service->replayClientMessage('u1', 'k1', 'hi'))->toBe($first)
        ->and($world->service->replayClientMessage('u3', 'k1', 'hi'))->toBeNull()
        ->and(fn () => $world->service->createUserMessage('hi', $otherOwner['conversation_ulid'], 'u2', clientMessageId: 'k1'))
        ->toThrow(ClientMessageConflictException::class);
});

it('creating with a stored key throws a conflict and leaves no new conversation, message, turn, event or job', function () {
    $world = clientKeyWorld();
    $world->service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1');

    expect(fn () => $world->service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1'))
        ->toThrow(ClientMessageConflictException::class)
        ->and($world->store->conversations)->toHaveCount(1)
        ->and($world->store->messages)->toHaveCount(1)
        ->and($world->store->turns)->toHaveCount(1)
        ->and($world->jobs)->toHaveCount(1);
});

it('records the key and its turn on the stored message only when a key is sent', function () {
    $world = clientKeyWorld();
    $keyed = $world->service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1');
    $world->service->createUserMessage('plain', $keyed['conversation_ulid'], 'u1');

    [$first, $second] = $world->store->messages;
    expect($first->client_message_id)->toBe('k1')
        ->and($first->client_message_owner_id)->toBe('u1')
        ->and($first->meta)->toBe(['turn_ulid' => $keyed['turn_ulid']])
        ->and($second->client_message_id)->toBeNull()
        ->and($second->client_message_owner_id)->toBeNull()
        ->and($second->meta)->toBeNull();
});

it('rejects a client message key without an owner', function () {
    $world = clientKeyWorld();

    expect(fn () => $world->service->createUserMessage('hi', clientMessageId: 'k1'))
        ->toThrow(InvalidArgumentException::class)
        ->and($world->store->messages)->toBe([]);
});
