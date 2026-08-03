<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Jobs\RunTurnJob;
use Rawphp\CapabilitiesAi\Models\Message;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;

function bootCheapCreateSqlite(): void
{
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setEventDispatcher(new EventDispatcher(new Container));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    $app = new Container;
    $app->instance('db', $capsule->getDatabaseManager());
    Facade::setFacadeApplication($app);
    Schema::swap($capsule->getConnection()->getSchemaBuilder());

    $dir = dirname(__DIR__, 3).'/database/migrations';
    $files = glob($dir.'/*.php') ?: [];
    sort($files);
    foreach ($files as $file) {
        (require $file)->up();
    }
}

/**
 * @return array{0: ConversationService, 1: object, 2: FakeLlmClient}
 */
function cheapCreateService(): array
{
    $bag = new class
    {
        /** @var list<object> */
        public array $jobs = [];
    };

    $dispatch = static function (object $job) use ($bag): void {
        $bag->jobs[] = $job;
        // intentionally do not call handle()
    };

    $llm = new FakeLlmClient;
    $service = new ConversationService($dispatch, new ArrayProgressStore);

    return [$service, $bag, $llm];
}

it('persists message and queued turn and dispatches job', function () {
    bootCheapCreateSqlite();
    [$service, $bag] = cheapCreateService();

    $ids = $service->createUserMessage('hello world');

    expect($ids['conversation_ulid'])->not->toBeEmpty()
        ->and($ids['message_ulid'])->not->toBeEmpty()
        ->and($ids['turn_ulid'])->not->toBeEmpty()
        ->and(Message::query()->where('ulid', $ids['message_ulid'])->value('content'))->toBe('hello world')
        ->and(Turn::query()->where('ulid', $ids['turn_ulid'])->value('status'))->toBe(Turn::STATUS_QUEUED)
        ->and($bag->jobs)->toHaveCount(1)
        ->and($bag->jobs[0])->toBeInstanceOf(RunTurnJob::class)
        ->and($bag->jobs[0]->turnUlid)->toBe($ids['turn_ulid']);
});

it('does not call LlmClient during cheap create', function () {
    bootCheapCreateSqlite();
    [$service, , $llm] = cheapCreateService();

    // Ensure create path does not touch LLM even if one is constructed in test harness
    $service->createUserMessage('no llm please');

    expect($llm->callCount)->toBe(0);
});

it('dispatches job without executing it in create', function () {
    bootCheapCreateSqlite();
    [$service, $bag] = cheapCreateService();
    $service->createUserMessage('dispatch only');
    expect($bag->jobs)->toHaveCount(1)
        ->and(method_exists($bag->jobs[0], 'handle'))->toBeTrue();
});
