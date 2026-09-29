<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\StaleTurnReaper;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryConversationStore;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryTurnClaim;

function reaperStore(bool $reset = false): InMemoryConversationStore
{
    static $store = null;
    if ($reset || $store === null) {
        $store = new InMemoryConversationStore;
    }

    return $store;
}

beforeEach(function () {
    reaperStore(reset: true);
});

function reaperFor(ArrayProgressStore $progress): StaleTurnReaper
{
    return new StaleTurnReaper($progress, new InMemoryTurnClaim(reaperStore()));
}

/**
 * @return array{conversation_ulid: string, turn_ulid: string, message_ulid: string}
 */
function seedQueuedTurnForReaper(): array
{
    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore, store: reaperStore());

    return $svc->createUserMessage('reaper seed');
}

it('fails queued turns older than threshold', function () {
    $frozenNow = Carbon::parse('2026-08-07 12:00:00');
    $ids = seedQueuedTurnForReaper();
    $ulid = $ids['turn_ulid'];

    reaperStore()->turn($ulid)->forceFill([
        'created_at' => $frozenNow->copy()->subMinutes(31),
        'updated_at' => $frozenNow->copy()->subMinutes(31),
    ]);

    $counts = reaperFor(new ArrayProgressStore)->reap(
        staleQueuedMinutes: 30,
        claimTtlSeconds: 120,
        runningGraceSeconds: 60,
        now: $frozenNow,
    );

    $turn = reaperStore()->turn($ulid);

    expect($counts['queued'])->toBe(1)
        ->and($counts['running'])->toBe(0)
        ->and($turn->status)->toBe(Turn::STATUS_FAILED)
        ->and($turn->error)->toBe('reaped: stale queued')
        ->and($turn->finished_at)->not->toBeNull();
});

it('fails running turns past max(claim_ttl, grace)', function () {
    $frozenNow = Carbon::parse('2026-08-07 12:00:00');
    $ids = seedQueuedTurnForReaper();
    $ulid = $ids['turn_ulid'];

    // max(120, 60) = 120s threshold; claimed 200s ago → stale
    reaperStore()->turn($ulid)->forceFill([
        'status' => Turn::STATUS_RUNNING,
        'claimed_at' => $frozenNow->copy()->subSeconds(200),
        'started_at' => $frozenNow->copy()->subSeconds(200),
        'updated_at' => $frozenNow->copy()->subSeconds(200),
    ]);

    $counts = reaperFor(new ArrayProgressStore)->reap(
        staleQueuedMinutes: 30,
        claimTtlSeconds: 120,
        runningGraceSeconds: 60,
        now: $frozenNow,
    );

    $turn = reaperStore()->turn($ulid);

    expect($counts['running'])->toBe(1)
        ->and($counts['queued'])->toBe(0)
        ->and($turn->status)->toBe(Turn::STATUS_FAILED)
        ->and($turn->error)->toBe('reaped: stale running claim')
        ->and($turn->finished_at)->not->toBeNull();
});

it('leaves fresh queued and running turns alone', function () {
    $frozenNow = Carbon::parse('2026-08-07 12:00:00');

    $queuedIds = seedQueuedTurnForReaper();
    reaperStore()->turn($queuedIds['turn_ulid'])->forceFill([
        'created_at' => $frozenNow->copy()->subMinutes(10),
        'updated_at' => $frozenNow->copy()->subMinutes(10),
    ]);

    $runningIds = seedQueuedTurnForReaper();
    reaperStore()->turn($runningIds['turn_ulid'])->forceFill([
        'status' => Turn::STATUS_RUNNING,
        'claimed_at' => $frozenNow->copy()->subSeconds(30),
        'started_at' => $frozenNow->copy()->subSeconds(30),
        'updated_at' => $frozenNow->copy()->subSeconds(30),
    ]);

    $counts = reaperFor(new ArrayProgressStore)->reap(
        staleQueuedMinutes: 30,
        claimTtlSeconds: 120,
        runningGraceSeconds: 60,
        now: $frozenNow,
    );

    expect($counts['queued'])->toBe(0)
        ->and($counts['running'])->toBe(0)
        ->and(reaperStore()->turn($queuedIds['turn_ulid'])->status)->toBe(Turn::STATUS_QUEUED)
        ->and(reaperStore()->turn($runningIds['turn_ulid'])->status)->toBe(Turn::STATUS_RUNNING);
});

it('uses the larger of claim_ttl and running grace for running cutoff', function () {
    $frozenNow = Carbon::parse('2026-08-07 12:00:00');
    $ids = seedQueuedTurnForReaper();

    // claim_ttl=60, grace=180 → threshold 180s; claimed 100s ago → still fresh
    reaperStore()->turn($ids['turn_ulid'])->forceFill([
        'status' => Turn::STATUS_RUNNING,
        'claimed_at' => $frozenNow->copy()->subSeconds(100),
    ]);

    $counts = reaperFor(new ArrayProgressStore)->reap(
        staleQueuedMinutes: 30,
        claimTtlSeconds: 60,
        runningGraceSeconds: 180,
        now: $frozenNow,
    );

    expect($counts['running'])->toBe(0)
        ->and(reaperStore()->turn($ids['turn_ulid'])->status)->toBe(Turn::STATUS_RUNNING);

    // claimed 200s ago → past 180s grace
    reaperStore()->turn($ids['turn_ulid'])->forceFill([
        'claimed_at' => $frozenNow->copy()->subSeconds(200),
    ]);

    $counts2 = reaperFor(new ArrayProgressStore)->reap(
        staleQueuedMinutes: 30,
        claimTtlSeconds: 60,
        runningGraceSeconds: 180,
        now: $frozenNow,
    );

    expect($counts2['running'])->toBe(1)
        ->and(reaperStore()->turn($ids['turn_ulid'])->status)->toBe(Turn::STATUS_FAILED);
});

it('appends error then terminal failed progress for each reaped turn', function () {
    $frozenNow = Carbon::parse('2026-08-07 12:00:00');
    $progress = new ArrayProgressStore;

    $queuedIds = seedQueuedTurnForReaper();
    reaperStore()->turn($queuedIds['turn_ulid'])->forceFill([
        'created_at' => $frozenNow->copy()->subMinutes(31),
    ]);

    $runningIds = seedQueuedTurnForReaper();
    reaperStore()->turn($runningIds['turn_ulid'])->forceFill([
        'status' => Turn::STATUS_RUNNING,
        'claimed_at' => $frozenNow->copy()->subSeconds(200),
    ]);
    $progress->append($runningIds['turn_ulid'], ['kind' => 'status', 'data' => ['status' => Turn::STATUS_RUNNING]]);

    reaperFor($progress)->reap(
        staleQueuedMinutes: 30,
        claimTtlSeconds: 120,
        runningGraceSeconds: 60,
        now: $frozenNow,
    );

    $queuedEvents = $progress->since($queuedIds['turn_ulid']);
    expect(array_column($queuedEvents, 'kind'))->toBe(['error', 'terminal'])
        ->and($queuedEvents[0]['data'])->toBe(['message' => 'reaped: stale queued'])
        ->and($queuedEvents[1]['data'])->toBe(['status' => Turn::STATUS_FAILED]);

    // Replay from the client's cursor after the last pre-reap event ends on terminal failed.
    $runningEvents = $progress->since($runningIds['turn_ulid'], 1);
    expect(array_column($runningEvents, 'kind'))->toBe(['error', 'terminal'])
        ->and($runningEvents[0]['data'])->toBe(['message' => 'reaped: stale running claim'])
        ->and($runningEvents[1]['data'])->toBe(['status' => Turn::STATUS_FAILED]);
});

it('appends no progress for turns it leaves alone', function () {
    $frozenNow = Carbon::parse('2026-08-07 12:00:00');
    $progress = new ArrayProgressStore;

    $ids = seedQueuedTurnForReaper();
    reaperStore()->turn($ids['turn_ulid'])->forceFill([
        'created_at' => $frozenNow->copy()->subMinutes(10),
    ]);

    reaperFor($progress)->reap(
        staleQueuedMinutes: 30,
        claimTtlSeconds: 120,
        runningGraceSeconds: 60,
        now: $frozenNow,
    );

    expect($progress->since($ids['turn_ulid']))->toBe([]);
});

it('skips a turn that left the stale state before its guarded update', function () {
    $frozenNow = Carbon::parse('2026-08-07 12:00:00');
    $progress = new ArrayProgressStore;

    $ids = seedQueuedTurnForReaper();
    $ulid = $ids['turn_ulid'];
    reaperStore()->turn($ulid)->forceFill([
        'created_at' => $frozenNow->copy()->subMinutes(31),
    ]);

    // A worker claims the turn right after the reaper selected it as stale: the guarded
    // update flips nothing (the SQL race itself is covered in EloquentTurnClaimTest).
    $claim = new class(reaperStore(), $ulid) extends InMemoryTurnClaim
    {
        public bool $raced = false;

        public function __construct(private readonly InMemoryConversationStore $rows, private readonly string $ulid)
        {
            parent::__construct($rows);
        }

        public function failStaleQueued(DateTimeInterface $createdBefore, string $error, DateTimeInterface $now): array
        {
            $this->raced = true;
            $this->rows->turn($this->ulid)->status = Turn::STATUS_RUNNING;

            return parent::failStaleQueued($createdBefore, $error, $now);
        }
    };

    $counts = (new StaleTurnReaper($progress, $claim))->reap(
        staleQueuedMinutes: 30,
        claimTtlSeconds: 120,
        runningGraceSeconds: 60,
        now: $frozenNow,
    );

    expect($claim->raced)->toBeTrue()
        ->and($counts['queued'])->toBe(0)
        ->and(reaperStore()->turn($ulid)->status)->toBe(Turn::STATUS_RUNNING)
        ->and($progress->since($ulid))->toBe([]);
});
