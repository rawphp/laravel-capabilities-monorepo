<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Domain\ClientMessageConflictException;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Message;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\EloquentConversationStore;

/**
 * Persistence adapter only: the SQL this store issues against the package's own tables.
 * Conversation rules (capacity, rate limit, closed, ownership outcomes) are unit-tested
 * against the in-memory fake in tests/Unit/Domain.
 */
function bootConversationStoreSqlite(): EloquentConversationStore
{
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $capsule->setEventDispatcher(new EventDispatcher(new Container));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $app = new Container;
    $app->instance('db', $capsule->getDatabaseManager());
    Facade::setFacadeApplication($app);
    Schema::swap($capsule->getConnection()->getSchemaBuilder());
    $files = glob(dirname(__DIR__, 3).'/database/migrations/*.php') ?: [];
    sort($files);
    foreach ($files as $file) {
        (require $file)->up();
    }

    return new EloquentConversationStore;
}

it('creates conversation, message and queued turn rows', function () {
    $store = bootConversationStoreSqlite();

    $conversation = $store->createConversation('C1', 'app-1', 'u1');
    $message = $store->createMessage($conversation, 'M1', 'user', 'hi');
    $turn = $store->createQueuedTurn($conversation, 'T1');

    expect(Conversation::query()->where('ulid', 'C1')->first()?->only(['app_id', 'user_id', 'status']))
        ->toBe(['app_id' => 'app-1', 'user_id' => 'u1', 'status' => 'open'])
        ->and(Message::query()->find($message->id)?->only(['conversation_id', 'role', 'content']))
        ->toBe(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'hi'])
        ->and(Turn::query()->find($turn->id)?->status)->toBe(Turn::STATUS_QUEUED);
});

it('finds a conversation only for its owner; a null owner matches ownerless rows only', function () {
    $store = bootConversationStoreSqlite();
    $owned = $store->createConversation('C1', null, 'u1');
    $ownerless = $store->createConversation('C2', null, null);

    expect($store->ownedConversation('C1', 'u1')->id)->toBe($owned->id)
        ->and($store->ownedConversation('C2', null)->id)->toBe($ownerless->id)
        ->and(fn () => $store->ownedConversation('C1', 'u2'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $store->ownedConversation('C1', null))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $store->ownedConversation('C2', 'u1'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $store->ownedConversation('MISSING', 'u1'))->toThrow(ModelNotFoundException::class);
});

it('lists a conversation\'s messages by created_at then id, and its proposals by id', function () {
    $store = bootConversationStoreSqlite();
    $conversation = $store->createConversation('C1', null, 'u1');
    $other = $store->createConversation('C2', null, 'u1');
    $late = $store->createMessage($conversation, 'M-late', 'user', 'late');
    $late->created_at = '2030-01-01 00:00:00';
    $late->save();
    $store->createMessage($conversation, 'M-a', 'user', 'a');
    $store->createMessage($conversation, 'M-b', 'assistant', 'b');
    $store->createMessage($other, 'M-other', 'user', 'other');
    $turn = $store->createQueuedTurn($conversation, 'T1');
    foreach (['P2', 'P1'] as $ulid) {
        Proposal::query()->create([
            'turn_id' => $turn->id,
            'conversation_id' => $conversation->id,
            'ulid' => $ulid,
            'type' => 'action',
            'payload' => [],
            'status' => Proposal::STATUS_PENDING,
        ]);
    }

    expect(array_map(static fn (Message $m): string => $m->ulid, $store->messages($conversation)))->toBe(['M-a', 'M-b', 'M-late'])
        ->and(array_map(static fn (Proposal $p): string => $p->ulid, $store->proposals($conversation)))->toBe(['P2', 'P1'])
        ->and($store->proposals($other))->toBe([]);
});

it('counts queued and running turns as active, globally and per conversation', function () {
    $store = bootConversationStoreSqlite();
    $a = $store->createConversation('C1', null, 'u1');
    $b = $store->createConversation('C2', null, 'u1');
    $store->createQueuedTurn($a, 'T1');
    Turn::query()->where('ulid', $store->createQueuedTurn($b, 'T2')->ulid)->update(['status' => Turn::STATUS_RUNNING]);
    foreach ([Turn::STATUS_COMPLETED, Turn::STATUS_FAILED, Turn::STATUS_CANCELLED] as $i => $status) {
        Turn::query()->where('ulid', $store->createQueuedTurn($b, "T-done-{$i}")->ulid)->update(['status' => $status]);
    }

    expect($store->activeTurnCount())->toBe(2)
        ->and($store->hasActiveTurns($a))->toBeTrue();

    Turn::query()->where('ulid', 'T1')->update(['status' => Turn::STATUS_COMPLETED]);

    expect($store->activeTurnCount())->toBe(1)
        ->and($store->hasActiveTurns($a))->toBeFalse()
        ->and($store->hasActiveTurns($b))->toBeTrue();
});

it('close persists status=closed', function () {
    $store = bootConversationStoreSqlite();
    $conversation = $store->createConversation('C1', null, 'u1');

    $store->close($conversation);

    expect(Conversation::query()->where('ulid', 'C1')->value('status'))->toBe('closed');
});

it('reads a turn with its conversation, and an owned turn only for its owner', function () {
    $store = bootConversationStoreSqlite();
    $owned = $store->createConversation('C1', null, 'u1');
    $ownerless = $store->createConversation('C2', null, null);
    $store->createQueuedTurn($owned, 'T1');
    $store->createQueuedTurn($ownerless, 'T2');

    expect($store->turn('T1')->conversation?->ulid)->toBe('C1')
        ->and($store->turn('T1')->relationLoaded('conversation'))->toBeTrue()
        ->and($store->ownedTurn('T1', 'u1')->conversation?->ulid)->toBe('C1')
        ->and(fn () => $store->turn('MISSING'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $store->ownedTurn('T1', 'u2'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $store->ownedTurn('T2', 'u1'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $store->ownedTurn('MISSING', 'u1'))->toThrow(ModelNotFoundException::class);
});

it('creates a pending proposal and reads it with its conversation and turn', function () {
    $store = bootConversationStoreSqlite();
    $conversation = $store->createConversation('C1', null, 'u1');
    $turn = $store->createQueuedTurn($conversation, 'T1');

    $store->createProposal($turn, 'P1', 'action', ['a' => 1], 'demo.cap', 'hash-1');
    $proposal = $store->proposal('P1');

    expect($proposal->only(['turn_id', 'conversation_id', 'type', 'payload', 'target_capability', 'schema_hash', 'status']))
        ->toBe([
            'turn_id' => $turn->id,
            'conversation_id' => $conversation->id,
            'type' => 'action',
            'payload' => ['a' => 1],
            'target_capability' => 'demo.cap',
            'schema_hash' => 'hash-1',
            'status' => Proposal::STATUS_PENDING,
        ])
        ->and($proposal->conversation?->ulid)->toBe('C1')
        ->and($proposal->turn?->ulid)->toBe('T1')
        ->and(fn () => $store->proposal('MISSING'))->toThrow(ModelNotFoundException::class);
});

it('reports proposal ownership only for the conversation owner', function () {
    $store = bootConversationStoreSqlite();
    $store->createProposal($store->createQueuedTurn($store->createConversation('C1', null, 'u1'), 'T1'), 'P1', 'action', [], null, null);
    $store->createProposal($store->createQueuedTurn($store->createConversation('C2', null, null), 'T2'), 'P2', 'action', [], null, null);

    expect($store->proposalOwnedBy('P1', 'u1'))->toBeTrue()
        ->and($store->proposalOwnedBy('P1', 'u2'))->toBeFalse()
        ->and($store->proposalOwnedBy('P2', ''))->toBeFalse()
        ->and($store->proposalOwnedBy('MISSING', 'u1'))->toBeFalse();
});

it('transitions a proposal only from the expected status', function () {
    $store = bootConversationStoreSqlite();
    $store->createProposal($store->createQueuedTurn($store->createConversation('C1', null, 'u1'), 'T1'), 'P1', 'action', [], 'demo.cap', null);

    expect($store->transitionProposal('P1', Proposal::STATUS_PENDING, ['status' => Proposal::STATUS_ACCEPTING]))->toBeTrue()
        ->and($store->transitionProposal('P1', Proposal::STATUS_PENDING, ['status' => Proposal::STATUS_REJECTED]))->toBeFalse()
        ->and($store->transitionProposal('P1', Proposal::STATUS_ACCEPTING, ['status' => Proposal::STATUS_FAILED, 'last_error' => 'x: y']))->toBeTrue()
        ->and($store->transitionProposal('MISSING', Proposal::STATUS_PENDING, ['status' => Proposal::STATUS_ACCEPTING]))->toBeFalse()
        ->and($store->proposal('P1')->only(['status', 'last_error']))->toBe(['status' => Proposal::STATUS_FAILED, 'last_error' => 'x: y']);
});

it('stores a client message key with its owner and turn, and finds it only for that owner', function () {
    $store = bootConversationStoreSqlite();
    $conversation = $store->createConversation('C1', null, 'u1');
    $store->createMessage($conversation, 'M0', 'user', 'plain');
    $message = $store->createMessage($conversation, 'M1', 'user', 'hi', 'k1', 'T1');

    $found = $store->clientMessage('u1', 'k1');

    expect(Message::query()->find($message->id)?->only(['client_message_id', 'client_message_owner_id', 'meta']))
        ->toBe(['client_message_id' => 'k1', 'client_message_owner_id' => 'u1', 'meta' => ['turn_ulid' => 'T1', 'sent' => false]])
        ->and(Message::query()->where('ulid', 'M0')->first()?->only(['client_message_id', 'client_message_owner_id', 'meta']))
        ->toBe(['client_message_id' => null, 'client_message_owner_id' => null, 'meta' => null])
        ->and($found?->ulid)->toBe('M1')
        ->and($found?->relationLoaded('conversation'))->toBeTrue()
        ->and($found?->conversation?->ulid)->toBe('C1')
        ->and($store->clientMessage('u2', 'k1'))->toBeNull()
        ->and($store->clientMessage('u1', 'k2'))->toBeNull();
});

it('lets the unique index hold one key per owner across conversations, and any number of unkeyed messages', function () {
    $store = bootConversationStoreSqlite();
    $mine = $store->createConversation('C1', null, 'u1');
    $alsoMine = $store->createConversation('C2', null, 'u1');
    $theirs = $store->createConversation('C3', null, 'u2');
    $store->createMessage($mine, 'M1', 'user', 'hi', 'k1', 'T1');
    $store->createMessage($theirs, 'M2', 'user', 'hi', 'k1', 'T2');
    $store->createMessage($mine, 'M3', 'user', 'a');
    $store->createMessage($mine, 'M4', 'user', 'b');

    expect(fn () => $store->createMessage($alsoMine, 'M5', 'user', 'hi', 'k1', 'T5'))
        ->toThrow(UniqueConstraintViolationException::class)
        ->and(Message::query()->count())->toBe(4);
});

it('rolls back every write in a failed transaction', function () {
    $store = bootConversationStoreSqlite();

    expect(fn () => $store->transaction(function () use ($store): never {
        $conversation = $store->createConversation('C1', null, 'u1');
        $store->createMessage($conversation, 'M1', 'user', 'hi');
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class, 'boom')
        ->and(Conversation::query()->count())->toBe(0)
        ->and(Message::query()->count())->toBe(0)
        ->and($store->transaction(static fn (): string => 'done'))->toBe('done');
});

it('a second send with one key on the database leaves one conversation, message and turn', function () {
    $store = bootConversationStoreSqlite();
    $jobs = 0;
    $service = new ConversationService(static function () use (&$jobs): void {
        $jobs++;
    }, new ArrayProgressStore, store: $store);
    $first = $service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1');

    expect(fn () => $service->createUserMessage('hi', userId: 'u1', clientMessageId: 'k1'))
        ->toThrow(ClientMessageConflictException::class)
        ->and(Conversation::query()->pluck('ulid')->all())->toBe([$first['conversation_ulid']])
        ->and(Message::query()->pluck('ulid')->all())->toBe([$first['message_ulid']])
        ->and(Turn::query()->pluck('ulid')->all())->toBe([$first['turn_ulid']])
        ->and($jobs)->toBe(1)
        ->and($service->replayClientMessage('u1', 'k1', 'hi'))->toBe($first);
});

it('creates an unkeyed message on a messages table that predates the client key columns', function () {
    $store = bootConversationStoreSqlite();
    $migration = require dirname(__DIR__, 3).'/database/migrations/2026_11_04_000001_add_client_message_id_to_capabilities_ai_messages_table.php';
    $migration->down();
    $conversation = $store->createConversation('C1', null, 'u1');

    $message = $store->createMessage($conversation, 'M1', 'user', 'hi');

    expect(Message::query()->find($message->id)?->content)->toBe('hi');
});

it('marks a client message sent', function () {
    $store = bootConversationStoreSqlite();
    $conversation = $store->createConversation('C1', null, 'u1');
    $message = $store->createMessage($conversation, 'M1', 'user', 'hi', 'k1', 'T1');

    $store->markClientMessageSent($message);

    expect(Message::query()->find($message->id)?->meta)->toBe(['turn_ulid' => 'T1', 'sent' => true]);
});

it('discards an unsent client message, its queued turn and optionally its conversation, only while the turn is queued', function () {
    $store = bootConversationStoreSqlite();
    $kept = $store->createConversation('C1', null, 'u1');
    $earlier = $store->createMessage($kept, 'M0', 'user', 'earlier');
    $message = $store->createMessage($kept, 'M1', 'user', 'hi', 'k1', 'T1');
    $turn = $store->createQueuedTurn($kept, 'T1');
    $fresh = $store->createConversation('C2', null, 'u1');
    $freshMessage = $store->createMessage($fresh, 'M2', 'user', 'hi', 'k2', 'T2');
    $freshTurn = $store->createQueuedTurn($fresh, 'T2');
    $claimed = $store->createConversation('C3', null, 'u1');
    $claimedMessage = $store->createMessage($claimed, 'M3', 'user', 'hi', 'k3', 'T3');
    $claimedTurn = $store->createQueuedTurn($claimed, 'T3');
    $claimedTurn->status = Turn::STATUS_RUNNING;
    $claimedTurn->save();

    expect($store->discardUnsentClientMessage($message, $turn, withConversation: false))->toBeTrue()
        ->and($store->discardUnsentClientMessage($freshMessage, $freshTurn, withConversation: true))->toBeTrue()
        ->and($store->discardUnsentClientMessage($claimedMessage, $claimedTurn, withConversation: true))->toBeFalse()
        ->and(Conversation::query()->orderBy('id')->pluck('ulid')->all())->toBe(['C1', 'C3'])
        ->and(Message::query()->orderBy('id')->pluck('ulid')->all())->toBe([$earlier->ulid, 'M3'])
        ->and(Turn::query()->pluck('ulid')->all())->toBe(['T3'])
        ->and($store->createMessage($kept, 'M4', 'user', 'hi', 'k1', 'T4')->ulid)->toBe('M4');
});
