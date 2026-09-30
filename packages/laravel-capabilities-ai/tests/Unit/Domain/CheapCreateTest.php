<?php

declare(strict_types=1);

use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Jobs\RunTurnJob;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Package;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryConversationStore;

/**
 * @return array{0: ConversationService, 1: object, 2: FakeLlmClient, 3: InMemoryConversationStore}
 */
function cheapCreateService(int $claimTtl = Package::DEFAULT_CLAIM_TTL): array
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
    $store = new InMemoryConversationStore;
    $service = new ConversationService($dispatch, new ArrayProgressStore, $claimTtl, store: $store);

    return [$service, $bag, $llm, $store];
}

it('persists message and queued turn and dispatches job', function () {
    [$service, $bag, , $store] = cheapCreateService();

    $ids = $service->createUserMessage('hello world');

    expect($ids['conversation_ulid'])->not->toBeEmpty()
        ->and($ids['message_ulid'])->not->toBeEmpty()
        ->and($ids['turn_ulid'])->not->toBeEmpty()
        ->and($store->messages[0]->ulid)->toBe($ids['message_ulid'])
        ->and($store->messages[0]->content)->toBe('hello world')
        ->and($store->messages[0]->role)->toBe('user')
        ->and($store->turn($ids['turn_ulid'])->status)->toBe(Turn::STATUS_QUEUED)
        ->and($store->conversation($ids['conversation_ulid'])->status)->toBe('open')
        ->and($bag->jobs)->toHaveCount(1)
        ->and($bag->jobs[0])->toBeInstanceOf(RunTurnJob::class)
        ->and($bag->jobs[0]->turnUlid)->toBe($ids['turn_ulid'])
        ->and($bag->jobs[0]->timeout)->toBe(120);
});

it('does not call LlmClient during cheap create', function () {
    [$service, , $llm] = cheapCreateService();

    // Ensure create path does not touch LLM even if one is constructed in test harness
    $service->createUserMessage('no llm please');

    expect($llm->callCount)->toBe(0);
});

it('dispatches job without executing it in create', function () {
    [$service, $bag] = cheapCreateService();
    $service->createUserMessage('dispatch only');
    expect($bag->jobs)->toHaveCount(1)
        ->and(method_exists($bag->jobs[0], 'handle'))->toBeTrue();
});

it('passes claim_ttl into job timeout on cheap create', function () {
    [$service, $bag] = cheapCreateService(claimTtl: 45);
    $service->createUserMessage('ttl');
    expect($bag->jobs)->toHaveCount(1)
        ->and($bag->jobs[0]->timeout)->toBe(45);
});

it('defaults job timeout to Package DEFAULT_CLAIM_TTL', function () {
    [$service, $bag] = cheapCreateService();
    $service->createUserMessage('default ttl');
    expect($bag->jobs[0]->timeout)->toBe(Package::DEFAULT_CLAIM_TTL);
});

it('appends the queued status before dispatch so a synchronous worker cannot end the stream on queued', function () {
    $progress = new ArrayProgressStore;
    // Sync queue driver / fast worker: the job starts before dispatch returns.
    $dispatch = static function (RunTurnJob $job) use ($progress): void {
        $progress->append($job->turnUlid, ['kind' => 'status', 'data' => ['status' => Turn::STATUS_RUNNING]]);
    };

    $ids = (new ConversationService($dispatch, $progress, store: new InMemoryConversationStore))->createUserMessage('sync');

    $statuses = array_map(
        static fn (array $e): mixed => $e['data']['status'] ?? null,
        $progress->since($ids['turn_ulid']),
    );
    expect($statuses)->toBe([Turn::STATUS_QUEUED, Turn::STATUS_RUNNING]);
});

it('rejects a non-callable dispatch at construction', function () {
    expect(fn () => new ConversationService('not-a-callable', new ArrayProgressStore, store: new InMemoryConversationStore))
        ->toThrow(InvalidArgumentException::class, 'dispatch must be callable');
});

it('rejects a non-positive claim TTL at construction', function (int $ttl) {
    expect(fn () => new ConversationService(static fn (object $job) => null, new ArrayProgressStore, $ttl, store: new InMemoryConversationStore))
        ->toThrow(InvalidArgumentException::class, 'claimTtl must be positive');
})->with([0, -5]);
