<?php

declare(strict_types=1);

use Rawphp\CapabilitiesAi\Contracts\ConversationContextProvider;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\TurnRunner;
use Rawphp\CapabilitiesAi\Jobs\RunTurnJob;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Package;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryConversationStore;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryTurnClaim;

/**
 * Per-test in-memory rows and the turn claim over them (no database).
 *
 * @return object{store: InMemoryConversationStore, claim: InMemoryTurnClaim}
 */
function jobWorld(bool $reset = false): object
{
    static $world = null;
    if ($reset || $world === null) {
        $store = new InMemoryConversationStore;
        $world = (object) ['store' => $store, 'claim' => new InMemoryTurnClaim($store)];
    }

    return $world;
}

beforeEach(function () {
    jobWorld(reset: true);
});

function jobConversations(): ConversationService
{
    return new ConversationService(static fn ($j) => null, new ArrayProgressStore, store: jobWorld()->store);
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
    $svc = jobConversations();
    $ids = $svc->createUserMessage('queue me');
    $runner = new TurnRunner(
        claim: jobWorld()->claim,
        store: jobWorld()->store,
        llm: new FakeLlmClient,
        context: jobHostContext(),
        tools: jobHostTools(),
        progress: new ArrayProgressStore,
    );

    $job = new RunTurnJob($ids['turn_ulid']);
    expect($job->tries)->toBe(1)->and($job->timeout)->toBe(Package::DEFAULT_CLAIM_TTL);
    $job->handle($runner, jobWorld()->claim, new ArrayProgressStore);

    expect(jobWorld()->store->turn($ids['turn_ulid'])->status)
        ->toBe(Turn::STATUS_COMPLETED);

    // second claim cannot re-run successfully
    $runner2 = new TurnRunner(
        claim: jobWorld()->claim,
        store: jobWorld()->store,
        llm: new FakeLlmClient,
        context: jobHostContext(),
        tools: jobHostTools(),
        progress: new ArrayProgressStore,
    );
    expect(fn () => $runner2->run($ids['turn_ulid']))
        ->toThrow(RuntimeException::class);
});

it('handle rethrows when claim fails', function () {
    $svc = jobConversations();
    $ids = $svc->createUserMessage('already claimed path');
    jobWorld()->store->turn($ids['turn_ulid'])->status = Turn::STATUS_RUNNING;

    $runner = new TurnRunner(
        claim: jobWorld()->claim,
        store: jobWorld()->store,
        llm: new FakeLlmClient,
        context: jobHostContext(),
        tools: jobHostTools(),
        progress: new ArrayProgressStore,
    );
    $progress = new ArrayProgressStore;
    $job = new RunTurnJob($ids['turn_ulid']);
    expect(fn () => $job->handle($runner, jobWorld()->claim, $progress))->toThrow(RuntimeException::class, 'Failed to claim turn');

    // Another worker owns the claim — the job must not fail it.
    expect(jobWorld()->store->turn($ids['turn_ulid'])->status)->toBe(Turn::STATUS_RUNNING)
        ->and($progress->since($ids['turn_ulid']))->toBe([]);
});

it('handle fails the still-queued turn with the real reason when host seams are unbound', function () {
    $svc = jobConversations();
    $ids = $svc->createUserMessage('no host seams');

    $runner = new TurnRunner(
        claim: jobWorld()->claim,
        store: jobWorld()->store,
        llm: new FakeLlmClient,
        context: null,
        tools: null,
        progress: new ArrayProgressStore,
    );
    $progress = new ArrayProgressStore;
    $job = new RunTurnJob($ids['turn_ulid']);

    expect(fn () => $job->handle($runner, jobWorld()->claim, $progress))
        ->toThrow(RuntimeException::class, 'ConversationContextProvider and ToolCatalog must be bound');

    $turn = jobWorld()->store->turn($ids['turn_ulid']);
    expect($turn->status)->toBe(Turn::STATUS_FAILED)
        ->and($turn->error)->toBe('ConversationContextProvider and ToolCatalog must be bound before running a turn')
        ->and($turn->finished_at)->not->toBeNull()
        ->and(array_map(static fn (array $e): array => [$e['kind'], $e['data']], $progress->since($ids['turn_ulid'])))
        ->toBe([
            ['error', ['message' => 'ConversationContextProvider and ToolCatalog must be bound before running a turn']],
            ['terminal', ['status' => Turn::STATUS_FAILED]],
        ]);
});

it('RunTurnJob wires handle(TurnRunner) to run', function () {
    $src = file_get_contents(dirname(__DIR__, 3).'/src/Jobs/RunTurnJob.php') ?: '';
    expect($src)->toContain('handle(TurnRunner $runner')
        ->and($src)->toContain('$runner->run($this->turnUlid)')
        ->and($src)->not->toContain('Claim + TurnRunner in ORI-349');
});
