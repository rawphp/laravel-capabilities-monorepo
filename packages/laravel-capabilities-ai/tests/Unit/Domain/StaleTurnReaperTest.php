<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\StaleTurnReaper;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;

function bootStaleReaperSqlite(): void
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

/**
 * @return array{conversation_ulid: string, turn_ulid: string, message_ulid: string}
 */
function seedQueuedTurnForReaper(): array
{
    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore);

    return $svc->createUserMessage('reaper seed');
}

it('fails queued turns older than threshold', function () {
    bootStaleReaperSqlite();
    $frozenNow = Carbon::parse('2026-08-07 12:00:00');
    $ids = seedQueuedTurnForReaper();
    $ulid = $ids['turn_ulid'];

    Turn::query()->where('ulid', $ulid)->update([
        'created_at' => $frozenNow->copy()->subMinutes(31),
        'updated_at' => $frozenNow->copy()->subMinutes(31),
    ]);

    $counts = (new StaleTurnReaper)->reap(
        staleQueuedMinutes: 30,
        claimTtlSeconds: 120,
        runningGraceSeconds: 60,
        now: $frozenNow,
    );

    $turn = Turn::query()->where('ulid', $ulid)->firstOrFail();

    expect($counts['queued'])->toBe(1)
        ->and($counts['running'])->toBe(0)
        ->and($turn->status)->toBe(Turn::STATUS_FAILED)
        ->and($turn->error)->toBe('reaped: stale queued')
        ->and($turn->finished_at)->not->toBeNull();
});

it('fails running turns past max(claim_ttl, grace)', function () {
    bootStaleReaperSqlite();
    $frozenNow = Carbon::parse('2026-08-07 12:00:00');
    $ids = seedQueuedTurnForReaper();
    $ulid = $ids['turn_ulid'];

    // max(120, 60) = 120s threshold; claimed 200s ago → stale
    Turn::query()->where('ulid', $ulid)->update([
        'status' => Turn::STATUS_RUNNING,
        'claimed_at' => $frozenNow->copy()->subSeconds(200),
        'started_at' => $frozenNow->copy()->subSeconds(200),
        'updated_at' => $frozenNow->copy()->subSeconds(200),
    ]);

    $counts = (new StaleTurnReaper)->reap(
        staleQueuedMinutes: 30,
        claimTtlSeconds: 120,
        runningGraceSeconds: 60,
        now: $frozenNow,
    );

    $turn = Turn::query()->where('ulid', $ulid)->firstOrFail();

    expect($counts['running'])->toBe(1)
        ->and($counts['queued'])->toBe(0)
        ->and($turn->status)->toBe(Turn::STATUS_FAILED)
        ->and($turn->error)->toBe('reaped: stale running claim')
        ->and($turn->finished_at)->not->toBeNull();
});

it('leaves fresh queued and running turns alone', function () {
    bootStaleReaperSqlite();
    $frozenNow = Carbon::parse('2026-08-07 12:00:00');

    $queuedIds = seedQueuedTurnForReaper();
    Turn::query()->where('ulid', $queuedIds['turn_ulid'])->update([
        'created_at' => $frozenNow->copy()->subMinutes(10),
        'updated_at' => $frozenNow->copy()->subMinutes(10),
    ]);

    $runningIds = seedQueuedTurnForReaper();
    Turn::query()->where('ulid', $runningIds['turn_ulid'])->update([
        'status' => Turn::STATUS_RUNNING,
        'claimed_at' => $frozenNow->copy()->subSeconds(30),
        'started_at' => $frozenNow->copy()->subSeconds(30),
        'updated_at' => $frozenNow->copy()->subSeconds(30),
    ]);

    $counts = (new StaleTurnReaper)->reap(
        staleQueuedMinutes: 30,
        claimTtlSeconds: 120,
        runningGraceSeconds: 60,
        now: $frozenNow,
    );

    expect($counts['queued'])->toBe(0)
        ->and($counts['running'])->toBe(0)
        ->and(Turn::query()->where('ulid', $queuedIds['turn_ulid'])->value('status'))->toBe(Turn::STATUS_QUEUED)
        ->and(Turn::query()->where('ulid', $runningIds['turn_ulid'])->value('status'))->toBe(Turn::STATUS_RUNNING);
});

it('uses the larger of claim_ttl and running grace for running cutoff', function () {
    bootStaleReaperSqlite();
    $frozenNow = Carbon::parse('2026-08-07 12:00:00');
    $ids = seedQueuedTurnForReaper();

    // claim_ttl=60, grace=180 → threshold 180s; claimed 100s ago → still fresh
    Turn::query()->where('ulid', $ids['turn_ulid'])->update([
        'status' => Turn::STATUS_RUNNING,
        'claimed_at' => $frozenNow->copy()->subSeconds(100),
    ]);

    $counts = (new StaleTurnReaper)->reap(
        staleQueuedMinutes: 30,
        claimTtlSeconds: 60,
        runningGraceSeconds: 180,
        now: $frozenNow,
    );

    expect($counts['running'])->toBe(0)
        ->and(Turn::query()->where('ulid', $ids['turn_ulid'])->value('status'))->toBe(Turn::STATUS_RUNNING);

    // claimed 200s ago → past 180s grace
    Turn::query()->where('ulid', $ids['turn_ulid'])->update([
        'claimed_at' => $frozenNow->copy()->subSeconds(200),
    ]);

    $counts2 = (new StaleTurnReaper)->reap(
        staleQueuedMinutes: 30,
        claimTtlSeconds: 60,
        runningGraceSeconds: 180,
        now: $frozenNow,
    );

    expect($counts2['running'])->toBe(1)
        ->and(Turn::query()->where('ulid', $ids['turn_ulid'])->value('status'))->toBe(Turn::STATUS_FAILED);
});
