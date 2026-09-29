<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;

function bootHistorySqlite(): void
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
}

it('history returns ordered messages', function () {
    bootHistorySqlite();
    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $ids = $svc->createUserMessage('first', userId: 'u1');
    $svc->createUserMessage('second', $ids['conversation_ulid'], userId: 'u1');

    $history = $svc->history($ids['conversation_ulid'], 'u1');

    expect($history['conversation_ulid'])->toBe($ids['conversation_ulid'])
        ->and($history['messages'])->toHaveCount(2)
        ->and($history['messages'][0]['content'])->toBe('first')
        ->and($history['messages'][1]['content'])->toBe('second');
});

it('history throws when conversation missing', function () {
    bootHistorySqlite();
    (new ConversationService(static fn ($j) => null, new ArrayProgressStore))->history('01MISSINGCONV00000000000', 'u1');
})->throws(ModelNotFoundException::class);

it('destroy closes conversation when no active turns', function () {
    bootHistorySqlite();
    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $ids = $svc->createUserMessage('bye', userId: 'u1');
    Turn::query()->where('ulid', $ids['turn_ulid'])->update(['status' => Turn::STATUS_COMPLETED]);

    $out = $svc->destroy($ids['conversation_ulid'], 'u1');

    expect($out['status'])->toBe('closed')
        ->and($out['closed'])->toBeTrue()
        ->and(Conversation::query()->where('ulid', $ids['conversation_ulid'])->value('status'))->toBe('closed');
});

it('destroy rejects when turn queued or running', function () {
    bootHistorySqlite();
    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $ids = $svc->createUserMessage('active', userId: 'u1');
    $svc->destroy($ids['conversation_ulid'], 'u1');
})->throws(RuntimeException::class);

it('destroy is idempotent when already closed', function () {
    bootHistorySqlite();
    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $ids = $svc->createUserMessage('x', userId: 'u1');
    Turn::query()->where('ulid', $ids['turn_ulid'])->update(['status' => Turn::STATUS_COMPLETED]);
    $svc->destroy($ids['conversation_ulid'], 'u1');
    $out = $svc->destroy($ids['conversation_ulid'], 'u1');

    expect($out['status'])->toBe('closed');
});

it('history hides another owner\'s conversation as not found', function () {
    bootHistorySqlite();
    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $ids = $svc->createUserMessage('private', userId: 'u1');

    $svc->history($ids['conversation_ulid'], 'u2');
})->throws(ModelNotFoundException::class);

it('history hides an ownerless conversation from every caller', function () {
    bootHistorySqlite();
    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $ids = $svc->createUserMessage('ownerless');

    $svc->history($ids['conversation_ulid'], 'u1');
})->throws(ModelNotFoundException::class);

it('destroy of another owner\'s conversation is not found and leaves it open', function () {
    bootHistorySqlite();
    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $ids = $svc->createUserMessage('keep me', userId: 'u1');
    Turn::query()->where('ulid', $ids['turn_ulid'])->update(['status' => Turn::STATUS_COMPLETED]);

    expect(fn () => $svc->destroy($ids['conversation_ulid'], 'u2'))->toThrow(ModelNotFoundException::class)
        ->and(Conversation::query()->where('ulid', $ids['conversation_ulid'])->value('status'))->toBe('open');
});

it('createUserMessage refuses to append to another owner\'s conversation', function () {
    bootHistorySqlite();
    $dispatched = [];
    $svc = new ConversationService(static function ($j) use (&$dispatched) {
        $dispatched[] = $j;
    }, new ArrayProgressStore);
    $ids = $svc->createUserMessage('mine', userId: 'u1');

    expect(fn () => $svc->createUserMessage('intrude', $ids['conversation_ulid'], userId: 'u2'))
        ->toThrow(ModelNotFoundException::class)
        ->and(Turn::query()->count())->toBe(1)
        ->and($dispatched)->toHaveCount(1);
});
