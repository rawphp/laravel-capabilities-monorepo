<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Contracts\ConversationContextProvider;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\TurnClaim;
use Rawphp\CapabilitiesAi\Domain\TurnRunner;
use Rawphp\CapabilitiesAi\Jobs\RunTurnJob;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Package;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;

function bootJobSqlite(): void
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

function jobHostContext(): ConversationContextProvider
{
    return new class implements ConversationContextProvider
    {
        public function messagesForTurn(string $conversationUlid, string $turnUlid): array
        {
            return [['role' => 'user', 'content' => 'hi']];
        }
    };
}

function jobHostTools(): ToolCatalog
{
    return new class implements ToolCatalog
    {
        public function toolsForTurn(string $conversationUlid, string $turnUlid): array
        {
            return [];
        }
    };
}

it('handle invokes TurnRunner and completes turn once', function () {
    bootJobSqlite();
    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $ids = $svc->createUserMessage('queue me');
    $runner = new TurnRunner(
        claim: new TurnClaim,
        llm: new FakeLlmClient,
        context: jobHostContext(),
        tools: jobHostTools(),
        progress: new ArrayProgressStore,
    );

    $job = new RunTurnJob($ids['turn_ulid']);
    expect($job->tries)->toBe(1)->and($job->timeout)->toBe(Package::DEFAULT_CLAIM_TTL);
    $job->handle($runner);

    expect(Turn::query()->where('ulid', $ids['turn_ulid'])->value('status'))
        ->toBe(Turn::STATUS_COMPLETED);

    // second claim cannot re-run successfully
    $runner2 = new TurnRunner(
        claim: new TurnClaim,
        llm: new FakeLlmClient,
        context: jobHostContext(),
        tools: jobHostTools(),
        progress: new ArrayProgressStore,
    );
    expect(fn () => $runner2->run($ids['turn_ulid']))
        ->toThrow(RuntimeException::class);
});

it('handle rethrows when claim fails', function () {
    bootJobSqlite();
    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $ids = $svc->createUserMessage('already claimed path');
    Turn::query()->where('ulid', $ids['turn_ulid'])->update(['status' => Turn::STATUS_RUNNING]);

    $runner = new TurnRunner(
        claim: new TurnClaim,
        llm: new FakeLlmClient,
        context: jobHostContext(),
        tools: jobHostTools(),
        progress: new ArrayProgressStore,
    );
    $job = new RunTurnJob($ids['turn_ulid']);
    expect(fn () => $job->handle($runner))->toThrow(RuntimeException::class);
});

it('RunTurnJob wires handle(TurnRunner) to run', function () {
    $src = file_get_contents(dirname(__DIR__, 3).'/src/Jobs/RunTurnJob.php') ?: '';
    expect($src)->toContain('handle(TurnRunner $runner)')
        ->and($src)->toContain('$runner->run($this->turnUlid)')
        ->and($src)->not->toContain('Claim + TurnRunner in ORI-349');
});
