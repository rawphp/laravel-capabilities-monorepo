<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\TurnService;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;

function bootTurnServiceSqlite(): ArrayProgressStore
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

    return new ArrayProgressStore;
}

function seedQueuedTurn(): array
{
    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $ids = $svc->createUserMessage('seed turn');
    $turn = Turn::query()->where('ulid', $ids['turn_ulid'])->firstOrFail();

    return [$ids, $turn];
}

it('show returns status and conversation_ulid', function () {
    $progress = bootTurnServiceSqlite();
    [$ids] = seedQueuedTurn();
    $service = new TurnService($progress);
    $out = $service->show($ids['turn_ulid']);

    expect($out['turn_ulid'])->toBe($ids['turn_ulid'])
        ->and($out['conversation_ulid'])->toBe($ids['conversation_ulid'])
        ->and($out['status'])->toBe(Turn::STATUS_QUEUED);
});

it('show throws when turn missing', function () {
    bootTurnServiceSqlite();
    (new TurnService(new ArrayProgressStore))->show('01MISSINGTURNULID0000000');
})->throws(ModelNotFoundException::class);

it('cancel queued turn becomes cancelled and writes progress', function () {
    $progress = bootTurnServiceSqlite();
    [$ids] = seedQueuedTurn();
    $service = new TurnService($progress);
    $out = $service->cancel($ids['turn_ulid']);

    expect($out['status'])->toBe(Turn::STATUS_CANCELLED)
        ->and(Turn::query()->where('ulid', $ids['turn_ulid'])->value('status'))->toBe(Turn::STATUS_CANCELLED);

    $events = $progress->since($ids['turn_ulid'], 0);
    $kinds = array_column($events, 'kind');
    expect($kinds)->toContain('status')->toContain('terminal');
});

it('cancel already-cancelled is idempotent', function () {
    $progress = bootTurnServiceSqlite();
    [$ids] = seedQueuedTurn();
    $service = new TurnService($progress);
    $service->cancel($ids['turn_ulid']);
    $out = $service->cancel($ids['turn_ulid']);

    expect($out['status'])->toBe(Turn::STATUS_CANCELLED);
});

it('cancel completed throws illegal transition', function () {
    $progress = bootTurnServiceSqlite();
    [$ids, $turn] = seedQueuedTurn();
    $turn->status = Turn::STATUS_COMPLETED;
    $turn->save();
    (new TurnService($progress))->cancel($ids['turn_ulid']);
})->throws(RuntimeException::class);

it('events 404s when turn missing', function () {
    bootTurnServiceSqlite();
    (new TurnService(new ArrayProgressStore))->events('01MISSINGTURNULID0000000');
})->throws(ModelNotFoundException::class);

it('events returns ProgressStore since for existing turn', function () {
    $progress = bootTurnServiceSqlite();
    [$ids] = seedQueuedTurn();
    $progress->append($ids['turn_ulid'], ['kind' => 'token', 'data' => ['t' => 1]]);
    $events = (new TurnService($progress))->events($ids['turn_ulid'], 0);

    expect($events)->not->toBeEmpty();
});
