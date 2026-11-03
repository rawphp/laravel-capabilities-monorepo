<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\CapabilitiesAi\Domain\ClientMessageConflictException;
use Rawphp\CapabilitiesAi\Domain\ClientMessageMismatchException;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Jobs\RunTurnJob;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Message;
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
        ->and($first->meta)->toBe(['turn_ulid' => $keyed['turn_ulid'], 'sent' => true])
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

/**
 * A send whose dispatch callable runs $onDispatch (e.g. throws, or acts like a sync-queue job).
 *
 * @return object{service: ConversationService, store: InMemoryConversationStore, jobs: ArrayObject<int, object>}
 */
function clientKeyDispatchWorld(callable $onDispatch, ?ProgressStore $progress = null): object
{
    $jobs = new ArrayObject;
    $store = new InMemoryConversationStore;
    $world = (object) ['store' => $store, 'jobs' => $jobs];
    $world->service = new ConversationService(static function (object $job) use ($onDispatch, $jobs, $world): void {
        $onDispatch($job, $world);
        $jobs[] = $job;
    }, $progress ?? new ArrayProgressStore, store: $store);

    return $world;
}

it('removes a keyed send whose job never reached the queue, so a retry with the key sends once', function () {
    $fail = true;
    $world = clientKeyDispatchWorld(static function () use (&$fail): void {
        if ($fail) {
            throw new RuntimeException('queue down');
        }
    });

    expect(fn () => $world->service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1'))
        ->toThrow(RuntimeException::class, 'queue down')
        ->and($world->service->replayClientMessage('u1', 'k1', 'hi'))->toBeNull()
        ->and($world->store->conversations)->toBe([])
        ->and($world->store->messages)->toBe([])
        ->and($world->store->turns)->toBe([]);

    $fail = false;
    $retry = $world->service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1');

    expect($world->service->replayClientMessage('u1', 'k1', 'hi'))->toBe($retry)
        ->and($world->store->messages)->toHaveCount(1)
        ->and($world->store->turns)->toHaveCount(1)
        ->and($world->jobs)->toHaveCount(1);
});

it('keeps an existing conversation when it removes a keyed send that never reached the queue', function () {
    $fail = false;
    $world = clientKeyDispatchWorld(static function () use (&$fail): void {
        if ($fail) {
            throw new RuntimeException('queue down');
        }
    });
    $earlier = $world->service->createUserMessage('earlier', userId: 'u1');
    $fail = true;

    expect(fn () => $world->service->createUserMessage('hi', $earlier['conversation_ulid'], 'u1', clientMessageId: 'k1'))
        ->toThrow(RuntimeException::class)
        ->and($world->store->conversations)->toHaveCount(1)
        ->and(array_map(static fn ($m) => $m->content, $world->store->messages))->toBe(['earlier'])
        ->and(array_map(static fn ($t) => $t->ulid, $world->store->turns))->toBe([$earlier['turn_ulid']]);
});

it('removes a keyed send whose queued progress event cannot be written, before any job is dispatched', function () {
    $progress = new class implements ProgressStore
    {
        public bool $fail = true;

        public function append(string $turnUlid, array $event): void
        {
            if ($this->fail) {
                throw new RuntimeException('redis down');
            }
        }

        public function since(string $turnUlid, int $cursor = 0): array
        {
            return [];
        }
    };
    $world = clientKeyDispatchWorld(static fn () => null, $progress);

    expect(fn () => $world->service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1'))
        ->toThrow(RuntimeException::class, 'redis down')
        ->and($world->jobs)->toHaveCount(0)
        ->and($world->service->replayClientMessage('u1', 'k1', 'hi'))->toBeNull()
        ->and($world->store->messages)->toBe([])
        ->and($world->store->turns)->toBe([]);

    $progress->fail = false;
    $retry = $world->service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1');

    expect($world->service->replayClientMessage('u1', 'k1', 'hi'))->toBe($retry)
        ->and($world->jobs)->toHaveCount(1);
});

it('keeps the key on a turn the job already claimed when dispatch throws, so a retry replays and runs nothing', function () {
    // A sync queue runs the job inside dispatch: it claims the turn, fails it, and rethrows.
    $world = clientKeyDispatchWorld(static function (RunTurnJob $job, object $world): void {
        $world->store->turn($job->turnUlid)->status = Turn::STATUS_FAILED;
        throw new RuntimeException('provider error after a tool ran');
    });

    expect(fn () => $world->service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1'))
        ->toThrow(RuntimeException::class, 'provider error after a tool ran');

    [$message] = $world->store->messages;
    $replayed = $world->service->replayClientMessage('u1', 'k1', 'hi');

    expect($replayed)->toBe([
        'conversation_ulid' => $world->store->conversations[0]->ulid,
        'message_ulid' => $message->ulid,
        'turn_ulid' => $world->store->turns[0]->ulid,
    ])
        ->and($message->meta['sent'] ?? null)->toBeTrue()
        ->and($world->store->turns)->toHaveCount(1);
});

it('answers a replay with a conflict while the first send has not finished dispatching', function () {
    $seen = null;
    $world = clientKeyDispatchWorld(static function (RunTurnJob $job, object $world) use (&$seen): void {
        try {
            $world->service->replayClientMessage('u1', 'k1', 'hi');
        } catch (Throwable $e) {
            $seen = $e;
        }
    });

    $ids = $world->service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1');

    expect($seen)->toBeInstanceOf(ClientMessageConflictException::class)
        ->and($world->service->replayClientMessage('u1', 'k1', 'hi'))->toBe($ids);
});

it('replays a key left pending once its turn has left queued (e.g. the reaper failed it)', function () {
    $world = clientKeyWorld();
    $ids = $world->service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1');
    // As if the process died between commit and dispatch: never marked sent.
    $world->store->messages[0]->meta = ['turn_ulid' => $ids['turn_ulid'], 'sent' => false];

    expect(fn () => $world->service->replayClientMessage('u1', 'k1', 'hi'))->toThrow(ClientMessageConflictException::class);

    $world->store->turn($ids['turn_ulid'])->status = Turn::STATUS_FAILED;

    expect($world->service->replayClientMessage('u1', 'k1', 'hi'))->toBe($ids);
});

it('sendUserMessage creates a new message, then replays the stored key without creating anything', function () {
    $world = clientKeyWorld();

    $first = $world->service->sendUserMessage('hi', null, 'u1', clientMessageId: 'k1');
    $repeat = $world->service->sendUserMessage('hi', null, 'u1', clientMessageId: 'k1');
    $plain = $world->service->sendUserMessage('plain', $first->ids['conversation_ulid'], 'u1');

    expect($first->created)->toBeTrue()
        ->and($repeat->created)->toBeFalse()
        ->and($repeat->ids)->toBe($first->ids)
        ->and($plain->created)->toBeTrue()
        ->and($world->store->messages)->toHaveCount(2)
        ->and($world->jobs)->toHaveCount(2)
        ->and(fn () => $world->service->sendUserMessage('other', null, 'u1', clientMessageId: 'k1'))
        ->toThrow(ClientMessageMismatchException::class);
});

it('rethrows a unique violation on an unkeyed send as is, not as a client key conflict', function () {
    $violation = new UniqueConstraintViolationException('memory', 'insert', [], new PDOException('UNIQUE'));
    $store = new class($violation) extends InMemoryConversationStore
    {
        public function __construct(private readonly Throwable $violation) {}

        public function createMessage(
            Conversation $conversation,
            string $ulid,
            string $role,
            string $content,
            ?string $clientMessageId = null,
            ?string $turnUlid = null,
        ): Message {
            throw $this->violation;
        }
    };
    $service = new ConversationService(static fn ($j) => null, new ArrayProgressStore, store: $store);

    $thrown = null;
    try {
        $service->createUserMessage('hi', userId: 'u1');
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBe($violation)
        ->and($store->conversations)->toBe([]);
});
