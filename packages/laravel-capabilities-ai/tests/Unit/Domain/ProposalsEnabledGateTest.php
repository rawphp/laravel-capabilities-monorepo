<?php

declare(strict_types=1);

use Rawphp\CapabilitiesAi\CapabilitiesAiServiceProvider;
use Rawphp\CapabilitiesAi\Contracts\ConversationContextProvider;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\TurnRunner;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;
use Rawphp\CapabilitiesAi\Support\ToolSchemaHash;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryConversationStore;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryTurnClaim;

/**
 * Fresh in-memory rows for one gate test (no database).
 */
function proposalGateStore(bool $reset = false): InMemoryConversationStore
{
    static $store = null;
    if ($reset || $store === null) {
        $store = new InMemoryConversationStore;
    }

    return $store;
}

beforeEach(function () {
    proposalGateStore(reset: true);
});

/**
 * @return array{turn_ulid: string, conversation_ulid: string}
 */
function enqueueProposalGateTurn(string $content = 'hi'): array
{
    $service = new ConversationService(static function ($job): void {
        // discard
    }, new ArrayProgressStore, store: proposalGateStore());
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
    $seeded = enqueueProposalGateTurn();
    $runner = new TurnRunner(
        claim: new InMemoryTurnClaim(proposalGateStore()),
        store: proposalGateStore(),
        llm: new FakeLlmClient([['content' => proposalFenceContent()]]),
        progress: new ArrayProgressStore,
        context: emptyContextProvider(),
        tools: emptyToolCatalog(),
        bus: null,
        proposalsEnabled: false,
    );
    $runner->run($seeded['turn_ulid']);
    expect(count(proposalGateStore()->proposals))->toBe(0);
});

it('creates proposal from fence when proposalsEnabled=true', function () {
    $seeded = enqueueProposalGateTurn();
    $runner = new TurnRunner(
        claim: new InMemoryTurnClaim(proposalGateStore()),
        store: proposalGateStore(),
        llm: new FakeLlmClient([['content' => proposalFenceContent()]]),
        progress: new ArrayProgressStore,
        context: emptyContextProvider(),
        tools: emptyToolCatalog(),
        bus: null,
        proposalsEnabled: true,
    );
    $runner->run($seeded['turn_ulid']);
    expect(count(proposalGateStore()->proposals))->toBe(1)
        ->and(proposalGateStore()->proposals[0]->target_capability)->toBe('x.y')
        ->and(proposalGateStore()->proposals[0]->status)->toBe(Proposal::STATUS_PENDING);
});

it('stamps the target tool schema hash on a fenced proposal at creation time', function () {
    $seeded = enqueueProposalGateTurn();
    $tool = ['name' => 'x.y', 'parameters' => ['type' => 'object', 'properties' => ['a' => ['type' => 'integer']]]];
    $tools = new class($tool) implements ToolCatalog
    {
        /** @param  array<string, mixed>  $tool */
        public function __construct(private array $tool) {}

        public function toolsForTurn(string $conversationUlid, string $turnUlid): array
        {
            return [$this->tool];
        }
    };
    $runner = new TurnRunner(
        claim: new InMemoryTurnClaim(proposalGateStore()),
        store: proposalGateStore(),
        llm: new FakeLlmClient([['content' => proposalFenceContent()]]),
        progress: new ArrayProgressStore,
        context: emptyContextProvider(),
        tools: $tools,
        bus: null,
    );
    $runner->run($seeded['turn_ulid']);

    expect(proposalGateStore()->proposals[0]->schema_hash)->toBe(ToolSchemaHash::of($tool));
});

it('leaves schema_hash null when the fenced target is not in the turn tool profile', function () {
    $seeded = enqueueProposalGateTurn();
    $runner = new TurnRunner(
        claim: new InMemoryTurnClaim(proposalGateStore()),
        store: proposalGateStore(),
        llm: new FakeLlmClient([['content' => proposalFenceContent()]]),
        progress: new ArrayProgressStore,
        context: emptyContextProvider(),
        tools: emptyToolCatalog(),
        bus: null,
    );
    $runner->run($seeded['turn_ulid']);

    expect(count(proposalGateStore()->proposals))->toBe(1)
        ->and(proposalGateStore()->proposals[0]->schema_hash)->toBeNull();
});

it('history returns proposals only when proposals are enabled', function (bool $enabled) {
    $store = new InMemoryConversationStore;
    $svc = new ConversationService(
        static fn ($j) => null,
        new ArrayProgressStore,
        claimTtl: 120,
        proposalsEnabled: $enabled,
        store: $store,
    );
    $ids = $svc->createUserMessage('hi', userId: 'u1');
    $store->addProposal($store->conversation($ids['conversation_ulid']), [
        'ulid' => 'PROP1',
        'type' => 'action',
        'target_capability' => 'demo.cap',
        'status' => Proposal::STATUS_PENDING,
    ]);

    $h = $svc->history($ids['conversation_ulid'], 'u1');

    expect($h)->toHaveKey('proposals')
        ->and($h['proposals'])->toBe($enabled ? [[
            'ulid' => 'PROP1',
            'status' => Proposal::STATUS_PENDING,
            'type' => 'action',
            'target_capability' => 'demo.cap',
        ]] : []);
})->with(['enabled' => [true], 'disabled' => [false]]);

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

function runProposalGateTurn(string $content, bool $proposalsEnabled): ArrayProgressStore
{
    $seeded = enqueueProposalGateTurn();
    $progress = new ArrayProgressStore;
    $runner = new TurnRunner(
        claim: new InMemoryTurnClaim(proposalGateStore()),
        store: proposalGateStore(),
        llm: new FakeLlmClient([['content' => $content]]),
        progress: $progress,
        context: emptyContextProvider(),
        tools: emptyToolCatalog(),
        proposalsEnabled: $proposalsEnabled,
    );
    expect($runner->run($seeded['turn_ulid'])->status)->toBe(Turn::STATUS_COMPLETED);

    return $progress;
}

function progressKinds(ArrayProgressStore $progress): array
{
    $turnUlid = proposalGateStore()->turns[0]->ulid;

    return array_column($progress->since($turnUlid), 'kind');
}

it('emits proposal_invalid progress when the proposal fence JSON does not decode', function () {
    $progress = runProposalGateTurn("ok\n```proposal\n{\"type\":\"action\",}\n```", proposalsEnabled: true);

    $kinds = progressKinds($progress);

    expect(count(proposalGateStore()->proposals))->toBe(0)
        ->and($kinds)->toContain('proposal_invalid')
        ->and(end($kinds))->toBe('terminal');
});

it('does not emit proposal_invalid when the fence is absent or valid', function (string $content) {
    $progress = runProposalGateTurn($content, proposalsEnabled: true);

    expect(progressKinds($progress))->not->toContain('proposal_invalid');
})->with([
    'absent' => 'plain answer',
    'valid' => proposalFenceContent(),
]);

it('does not emit proposal_invalid when proposals are disabled', function () {
    $progress = runProposalGateTurn("ok\n```proposal\n{bad}\n```", proposalsEnabled: false);

    expect(progressKinds($progress))->not->toContain('proposal_invalid');
});
