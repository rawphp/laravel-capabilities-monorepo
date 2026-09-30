<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Rawphp\CapabilitiesAi\Domain\ConversationClosedException;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryConversationStore;

/**
 * @return array{0: ConversationService, 1: InMemoryConversationStore}
 */
function historyService(?callable $dispatch = null): array
{
    $store = new InMemoryConversationStore;

    return [new ConversationService($dispatch ?? static fn ($j) => null, new ArrayProgressStore, store: $store), $store];
}

it('history returns ordered messages', function () {
    [$svc] = historyService();
    $ids = $svc->createUserMessage('first', userId: 'u1');
    $svc->createUserMessage('second', $ids['conversation_ulid'], userId: 'u1');

    $history = $svc->history($ids['conversation_ulid'], 'u1');

    expect($history['conversation_ulid'])->toBe($ids['conversation_ulid'])
        ->and($history['messages'])->toHaveCount(2)
        ->and($history['messages'][0]['content'])->toBe('first')
        ->and($history['messages'][0]['role'])->toBe('user')
        ->and($history['messages'][0]['created_at'])->toBeString()
        ->and($history['messages'][1]['content'])->toBe('second')
        ->and($history['proposals'])->toBe([]);
});

it('history throws when conversation missing', function () {
    [$svc] = historyService();
    $svc->history('01MISSINGCONV00000000000', 'u1');
})->throws(ModelNotFoundException::class);

it('destroy closes conversation when no active turns', function () {
    [$svc, $store] = historyService();
    $ids = $svc->createUserMessage('bye', userId: 'u1');
    $store->turn($ids['turn_ulid'])->status = Turn::STATUS_COMPLETED;

    $out = $svc->destroy($ids['conversation_ulid'], 'u1');

    expect($out['status'])->toBe('closed')
        ->and($out['closed'])->toBeTrue()
        ->and($store->conversation($ids['conversation_ulid'])->status)->toBe('closed');
});

it('destroy rejects when turn queued or running', function (string $status) {
    [$svc, $store] = historyService();
    $ids = $svc->createUserMessage('active', userId: 'u1');
    $store->turn($ids['turn_ulid'])->status = $status;

    expect(fn () => $svc->destroy($ids['conversation_ulid'], 'u1'))->toThrow(RuntimeException::class)
        ->and($store->conversation($ids['conversation_ulid'])->status)->toBe('open');
})->with([Turn::STATUS_QUEUED, Turn::STATUS_RUNNING]);

it('destroy is idempotent when already closed', function () {
    [$svc, $store] = historyService();
    $ids = $svc->createUserMessage('x', userId: 'u1');
    $store->turn($ids['turn_ulid'])->status = Turn::STATUS_COMPLETED;
    $svc->destroy($ids['conversation_ulid'], 'u1');
    $out = $svc->destroy($ids['conversation_ulid'], 'u1');

    expect($out['status'])->toBe('closed');
});

it('history hides another owner\'s conversation as not found', function () {
    [$svc] = historyService();
    $ids = $svc->createUserMessage('private', userId: 'u1');

    $svc->history($ids['conversation_ulid'], 'u2');
})->throws(ModelNotFoundException::class);

it('history hides an ownerless conversation from every caller', function () {
    [$svc] = historyService();
    $ids = $svc->createUserMessage('ownerless');

    $svc->history($ids['conversation_ulid'], 'u1');
})->throws(ModelNotFoundException::class);

it('destroy of another owner\'s conversation is not found and leaves it open', function () {
    [$svc, $store] = historyService();
    $ids = $svc->createUserMessage('keep me', userId: 'u1');
    $store->turn($ids['turn_ulid'])->status = Turn::STATUS_COMPLETED;

    expect(fn () => $svc->destroy($ids['conversation_ulid'], 'u2'))->toThrow(ModelNotFoundException::class)
        ->and($store->conversation($ids['conversation_ulid'])->status)->toBe('open');
});

it('createUserMessage refuses to append to another owner\'s conversation', function () {
    $dispatched = [];
    [$svc, $store] = historyService(static function ($j) use (&$dispatched) {
        $dispatched[] = $j;
    });
    $ids = $svc->createUserMessage('mine', userId: 'u1');

    expect(fn () => $svc->createUserMessage('intrude', $ids['conversation_ulid'], userId: 'u2'))
        ->toThrow(ModelNotFoundException::class)
        ->and($store->turns)->toHaveCount(1)
        ->and($dispatched)->toHaveCount(1);
});

it('createUserMessage refuses a closed conversation before persisting or dispatching', function () {
    $dispatched = 0;
    [$svc, $store] = historyService(static function () use (&$dispatched): void {
        $dispatched++;
    });
    $ids = $svc->createUserMessage('bye', userId: 'u1');
    $store->turn($ids['turn_ulid'])->status = Turn::STATUS_COMPLETED;
    $svc->destroy($ids['conversation_ulid'], 'u1');

    expect(fn () => $svc->createUserMessage('again', $ids['conversation_ulid'], 'u1'))
        ->toThrow(ConversationClosedException::class, "Conversation {$ids['conversation_ulid']} is closed")
        ->and($dispatched)->toBe(1)
        ->and($store->messages)->toHaveCount(1)
        ->and($store->turns)->toHaveCount(1);
});
