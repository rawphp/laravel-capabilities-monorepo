<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesAi\Contracts\ConversationContextProvider;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\TurnClaim;
use Rawphp\CapabilitiesAi\Domain\TurnRunner;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;

function bootTurnSqlite(): void
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

function enqueueTurn(string $content = 'hi'): string
{
    $service = new ConversationService(static function ($job): void {
        // discard
    }, new ArrayProgressStore);
    $ids = $service->createUserMessage($content);

    return $ids['turn_ulid'];
}

function recordingBus(): object
{
    return new class implements CapabilityBus
    {
        public int $invokes = 0;

        public string $lastName = '';

        /** @var array<string, mixed> */
        public array $lastInput = [];

        public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
        {
            $this->invokes++;
            $this->lastName = $nameOrAlias;
            $this->lastInput = $input;

            return CapabilityResult::ok(['ok' => true]);
        }

        public function catalog(): CatalogPresenter
        {
            throw new RuntimeException('catalog not used in turn tests');
        }
    };
}

it('double-claim of same turn: second claim fails', function () {
    bootTurnSqlite();
    $turnUlid = enqueueTurn();
    $claim = new TurnClaim;
    $first = $claim->claim($turnUlid, 'worker-a');
    $second = $claim->claim($turnUlid, 'worker-b');
    expect($first)->not->toBeNull()
        ->and($first->status)->toBe(Turn::STATUS_RUNNING)
        ->and($second)->toBeNull();
});

it('FakeLlmClient text-only turn reaches completed with terminal after DB', function () {
    bootTurnSqlite();
    $turnUlid = enqueueTurn('tell me a joke');
    $progress = new ArrayProgressStore;
    $context = new class implements ConversationContextProvider
    {
        public function messagesForTurn(string $conversationUlid, string $turnUlid): array
        {
            return [['role' => 'user', 'content' => 'tell me a joke']];
        }
    };
    $tools = new class implements ToolCatalog
    {
        public function toolsForTurn(string $conversationUlid, string $turnUlid): array
        {
            return [];
        }
    };
    $runner = new TurnRunner(
        claim: new TurnClaim,
        llm: new FakeLlmClient([['content' => 'knock knock']]),
        context: $context,
        tools: $tools,
        progress: $progress,
    );
    $turn = $runner->run($turnUlid);
    expect($turn->status)->toBe(Turn::STATUS_COMPLETED);
    $events = $progress->since($turnUlid);
    $kinds = array_column($events, 'kind');
    expect($kinds)->toContain('terminal');
    expect(end($kinds))->toBe('terminal');
});

it('tool call path invokes CapabilityBus exactly once with expected name/payload', function () {
    bootTurnSqlite();
    $turnUlid = enqueueTurn('use tool');
    $bus = recordingBus();
    $llm = new FakeLlmClient([
        ['tool_calls' => [['name' => 'demo.tool', 'arguments' => ['x' => 1]]]],
        ['content' => 'done'],
    ]);
    $context = new class implements ConversationContextProvider
    {
        public function messagesForTurn(string $conversationUlid, string $turnUlid): array
        {
            return [['role' => 'user', 'content' => 'use tool']];
        }
    };
    $tools = new class implements ToolCatalog
    {
        public function toolsForTurn(string $conversationUlid, string $turnUlid): array
        {
            return [['name' => 'demo.tool']];
        }
    };
    $runner = new TurnRunner(
        claim: new TurnClaim,
        llm: $llm,
        context: $context,
        tools: $tools,
        bus: $bus,
        progress: new ArrayProgressStore,
    );
    $turn = $runner->run($turnUlid);
    expect($turn->status)->toBe(Turn::STATUS_COMPLETED)
        ->and($bus->invokes)->toBe(1)
        ->and($bus->lastName)->toBe('demo.tool')
        ->and($bus->lastInput)->toBe(['x' => 1]);
});

it('missing ContextProvider/ToolCatalog fails closed', function () {
    bootTurnSqlite();
    $turnUlid = enqueueTurn();
    $runner = new TurnRunner(
        claim: new TurnClaim,
        llm: new FakeLlmClient,
        context: null,
        tools: null,
        progress: new ArrayProgressStore
    );
    expect(fn () => $runner->run($turnUlid))
        ->toThrow(RuntimeException::class, 'ConversationContextProvider and ToolCatalog must be bound');
});

it('does not overwrite cancelled status with completed (cooperative cancel)', function () {
    bootTurnSqlite();
    $turnUlid = enqueueTurn();
    $progress = new ArrayProgressStore;
    $llm = new class implements LlmClient
    {
        public function complete(array $messages, array $tools = []): array
        {
            Turn::query()->where('ulid', $GLOBALS['coop_turn_ulid'])->update([
                'status' => Turn::STATUS_CANCELLED,
                'finished_at' => Carbon::now()->toDateTimeString(),
            ]);

            return ['content' => 'too late', 'tool_calls' => []];
        }
    };
    $GLOBALS['coop_turn_ulid'] = $turnUlid;
    $context = new class implements ConversationContextProvider
    {
        public function messagesForTurn(string $conversationUlid, string $turnUlid): array
        {
            return [['role' => 'user', 'content' => 'hi']];
        }
    };
    $tools = new class implements ToolCatalog
    {
        public function toolsForTurn(string $conversationUlid, string $turnUlid): array
        {
            return [];
        }
    };
    $runner = new TurnRunner(
        claim: new TurnClaim,
        llm: $llm,
        context: $context,
        tools: $tools,
        progress: $progress,
    );
    $turn = $runner->run($turnUlid);
    expect($turn->status)->toBe(Turn::STATUS_CANCELLED);
    $terminals = array_values(array_filter(
        $progress->since($turnUlid, 0),
        static fn (array $e): bool => ($e['kind'] ?? '') === 'terminal'
            && (($e['data']['status'] ?? '') === Turn::STATUS_COMPLETED)
    ));
    expect($terminals)->toBeEmpty();
});
