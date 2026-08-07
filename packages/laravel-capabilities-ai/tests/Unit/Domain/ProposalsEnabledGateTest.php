<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\CapabilitiesAiServiceProvider;
use Rawphp\CapabilitiesAi\Contracts\ConversationContextProvider;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\TurnClaim;
use Rawphp\CapabilitiesAi\Domain\TurnRunner;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;

function bootProposalsGateSqlite(): void
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
 * @return array{turn_ulid: string, conversation_ulid: string}
 */
function enqueueProposalGateTurn(string $content = 'hi'): array
{
    $service = new ConversationService(static function ($job): void {
        // discard
    }, new ArrayProgressStore);
    $ids = $service->createUserMessage($content);

    return [
        'turn_ulid' => $ids['turn_ulid'],
        'conversation_ulid' => $ids['conversation_ulid'],
    ];
}

function emptyContextProvider(): ConversationContextProvider
{
    return new class implements ConversationContextProvider
    {
        public function messagesForTurn(string $conversationUlid, string $turnUlid): array
        {
            return [['role' => 'user', 'content' => 'hi']];
        }
    };
}

function emptyToolCatalog(): ToolCatalog
{
    return new class implements ToolCatalog
    {
        public function toolsForTurn(string $conversationUlid, string $turnUlid): array
        {
            return [];
        }
    };
}

function proposalFenceContent(): string
{
    return "ok\n```proposal\n{\"type\":\"action\",\"target_capability\":\"x.y\",\"payload\":{}}\n```";
}

it('skips proposal fence extract when proposalsEnabled=false', function () {
    bootProposalsGateSqlite();
    $seeded = enqueueProposalGateTurn();
    $runner = new TurnRunner(
        claim: new TurnClaim,
        llm: new FakeLlmClient([['content' => proposalFenceContent()]]),
        progress: new ArrayProgressStore,
        context: emptyContextProvider(),
        tools: emptyToolCatalog(),
        bus: null,
        proposalsEnabled: false,
    );
    $runner->run($seeded['turn_ulid']);
    expect(Proposal::query()->count())->toBe(0);
});

it('creates proposal from fence when proposalsEnabled=true', function () {
    bootProposalsGateSqlite();
    $seeded = enqueueProposalGateTurn();
    $runner = new TurnRunner(
        claim: new TurnClaim,
        llm: new FakeLlmClient([['content' => proposalFenceContent()]]),
        progress: new ArrayProgressStore,
        context: emptyContextProvider(),
        tools: emptyToolCatalog(),
        bus: null,
        proposalsEnabled: true,
    );
    $runner->run($seeded['turn_ulid']);
    expect(Proposal::query()->count())->toBe(1)
        ->and(Proposal::query()->first()?->target_capability)->toBe('x.y')
        ->and(Proposal::query()->first()?->status)->toBe(Proposal::STATUS_PENDING);
});

it('history returns empty proposals when service constructed with proposals disabled', function () {
    bootProposalsGateSqlite();
    $svc = new ConversationService(
        static fn ($j) => null,
        new ArrayProgressStore,
        claimTtl: 120,
        proposalsEnabled: false,
    );
    $ids = $svc->createUserMessage('hi');
    $h = $svc->history($ids['conversation_ulid']);
    expect($h)->toHaveKey('proposals')
        ->and($h['proposals'])->toBe([]);
});

it('history loads proposals when proposals enabled', function () {
    bootProposalsGateSqlite();
    $svc = new ConversationService(
        static fn ($j) => null,
        new ArrayProgressStore,
        claimTtl: 120,
        proposalsEnabled: true,
    );
    $ids = $svc->createUserMessage('hi');
    $turn = Turn::query()->where('ulid', $ids['turn_ulid'])->firstOrFail();
    $conversation = Conversation::query()->where('ulid', $ids['conversation_ulid'])->firstOrFail();
    Proposal::query()->create([
        'turn_id' => $turn->id,
        'conversation_id' => $conversation->id,
        'ulid' => strtoupper(bin2hex(random_bytes(13))),
        'type' => 'action',
        'payload' => [],
        'target_capability' => 'demo.cap',
        'status' => Proposal::STATUS_PENDING,
    ]);

    $h = $svc->history($ids['conversation_ulid']);
    expect($h['proposals'])->toHaveCount(1)
        ->and($h['proposals'][0]['target_capability'])->toBe('demo.cap')
        ->and($h['proposals'][0]['status'])->toBe(Proposal::STATUS_PENDING);
});

it('proposalsEnabled defaults true and respects config', function () {
    expect(CapabilitiesAiServiceProvider::proposalsEnabled([]))->toBeTrue()
        ->and(CapabilitiesAiServiceProvider::proposalsEnabled(['proposals' => ['enabled' => true]]))->toBeTrue()
        ->and(CapabilitiesAiServiceProvider::proposalsEnabled(['proposals' => ['enabled' => false]]))->toBeFalse();
});

it('proposal accept/reject routes live in dedicated proposals route file', function () {
    $main = dirname(__DIR__, 3).'/routes/capabilities-ai.php';
    $proposals = dirname(__DIR__, 3).'/routes/capabilities-ai-proposals.php';
    expect(is_file($proposals))->toBeTrue();
    $mainSrc = file_get_contents($main) ?: '';
    $propSrc = file_get_contents($proposals) ?: '';
    expect($mainSrc)->not->toContain('acceptProposal')
        ->and($mainSrc)->not->toContain('rejectProposal')
        ->and($propSrc)->toContain('acceptProposal')
        ->and($propSrc)->toContain('rejectProposal')
        ->and($propSrc)->toContain('capabilities-ai.proposals.accept');
});

it('provider bootRoutes gates proposal routes on proposals.enabled', function () {
    $path = dirname(__DIR__, 3).'/src/CapabilitiesAiServiceProvider.php';
    $src = file_get_contents($path) ?: '';
    expect($src)->toContain('proposalsEnabled')
        ->and($src)->toContain('capabilities-ai-proposals.php')
        ->and($src)->toContain('public static function proposalsEnabled');
});
