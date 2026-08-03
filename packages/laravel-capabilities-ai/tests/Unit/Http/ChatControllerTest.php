<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\TurnService;
use Rawphp\CapabilitiesAi\Http\ChatController;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;

function bootHttpSqlite(): ArrayProgressStore
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

it('history returns 200 with messages', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('hello');
    $response = (new ChatController)->history($ids['conversation_ulid'], $conversations);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['messages'][0]['content'])->toBe('hello');
});

it('history returns 404 when missing', function () {
    bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null);
    $response = (new ChatController)->history('01MISSINGCONV00000000000', $conversations);
    expect($response->getStatusCode())->toBe(404);
});

it('showTurn and cancelTurn happy path', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('t');
    $turns = new TurnService($progress);

    $show = (new ChatController)->showTurn($ids['turn_ulid'], $turns);
    expect($show->getStatusCode())->toBe(200)
        ->and($show->getData(true)['status'])->toBe(Turn::STATUS_QUEUED);

    $cancel = (new ChatController)->cancelTurn($ids['turn_ulid'], $turns);
    expect($cancel->getStatusCode())->toBe(200)
        ->and($cancel->getData(true)['status'])->toBe(Turn::STATUS_CANCELLED);
});

it('cancelTurn returns 409 on illegal transition', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('done');
    Turn::query()->where('ulid', $ids['turn_ulid'])->update(['status' => Turn::STATUS_COMPLETED]);
    $response = (new ChatController)->cancelTurn($ids['turn_ulid'], new TurnService($progress));
    expect($response->getStatusCode())->toBe(409);
});

it('turnEvents passes cursor and returns events', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('e');
    $progress->append($ids['turn_ulid'], ['kind' => 'token', 'data' => ['t' => 1]]);
    $request = Request::create('/events', 'GET', ['cursor' => 0]);
    $response = (new ChatController)->turnEvents($request, $ids['turn_ulid'], new TurnService($progress));
    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['events'])->not->toBeEmpty();
});

it('destroyConversation 409 when active turns and 200 when closed', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('active');
    $controller = new ChatController;

    expect($controller->destroyConversation($ids['conversation_ulid'], $conversations)->getStatusCode())
        ->toBe(409);

    Turn::query()->where('ulid', $ids['turn_ulid'])->update(['status' => Turn::STATUS_COMPLETED]);
    $ok = $controller->destroyConversation($ids['conversation_ulid'], $conversations);
    expect($ok->getStatusCode())->toBe(200)
        ->and($ok->getData(true)['status'])->toBe('closed');
});

it('controller source delegates without Eloquent creates', function () {
    $src = file_get_contents(dirname(__DIR__, 3).'/src/Http/ChatController.php') ?: '';
    expect($src)->toContain('TurnService')
        ->and($src)->toContain('ConversationService')
        ->and($src)->not->toContain('::query()->create')
        ->and($src)->not->toContain('::query()->update')
        ->and($src)->not->toContain('::query()->where');
});
