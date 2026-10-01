<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\EloquentConversationStore;
use Rawphp\CapabilitiesAi\Support\EloquentTurnClaim;

/**
 * Persistence adapter only: the compare-and-set SQL this claim issues against the package's
 * own turns table. Turn rules (runner, cancel, reaper, job) are unit-tested against the
 * in-memory fake in tests/Unit/Domain and tests/Unit/Jobs.
 */
function bootTurnClaimSqlite(): EloquentTurnClaim
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

    return new EloquentTurnClaim;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function seedClaimTurn(string $ulid, array $attributes = []): void
{
    $store = new EloquentConversationStore;
    $store->createQueuedTurn($store->createConversation('C-'.$ulid, null, 'u1'), $ulid);
    if ($attributes !== []) {
        Turn::query()->where('ulid', $ulid)->update($attributes);
    }
}

function claimTurnRow(string $ulid): Turn
{
    return Turn::query()->where('ulid', $ulid)->firstOrFail();
}

afterEach(function () {
    Carbon::setTestNow();
});

it('double-claim of same turn: second claim fails', function () {
    $claim = bootTurnClaimSqlite();
    seedClaimTurn('T1');

    $first = $claim->claim('T1', 'worker-a');
    $second = $claim->claim('T1', 'worker-b');

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and(claimTurnRow('T1')->only(['status', 'claim_owner']))
        ->toBe(['status' => Turn::STATUS_RUNNING, 'claim_owner' => 'worker-a'])
        ->and(claimTurnRow('T1')->claimed_at)->not->toBeNull()
        ->and(claimTurnRow('T1')->started_at)->not->toBeNull()
        ->and($claim->claim('MISSING', 'worker-a'))->toBeFalse();
});

it('completes and fails only a running turn, storing usage and finished_at', function () {
    $claim = bootTurnClaimSqlite();
    seedClaimTurn('T-done', ['status' => Turn::STATUS_RUNNING]);
    seedClaimTurn('T-bad', ['status' => Turn::STATUS_RUNNING]);
    seedClaimTurn('T-cancelled', ['status' => Turn::STATUS_CANCELLED]);
    $usage = [['latency_ms' => 5, 'input_tokens' => 1]];

    expect($claim->complete('T-done', $usage))->toBeTrue()
        ->and($claim->complete('T-done', $usage))->toBeFalse()
        ->and($claim->fail('T-bad', 'boom', $usage))->toBeTrue()
        ->and($claim->complete('T-cancelled', $usage))->toBeFalse()
        ->and($claim->fail('T-cancelled', 'boom', $usage))->toBeFalse()
        ->and(claimTurnRow('T-done')->only(['status', 'usage']))->toBe(['status' => Turn::STATUS_COMPLETED, 'usage' => $usage])
        ->and(claimTurnRow('T-done')->finished_at)->not->toBeNull()
        ->and(claimTurnRow('T-bad')->only(['status', 'error', 'usage']))->toBe(['status' => Turn::STATUS_FAILED, 'error' => 'boom', 'usage' => $usage])
        ->and(claimTurnRow('T-cancelled')->status)->toBe(Turn::STATUS_CANCELLED);
});

it('records usage whatever the status and probes a running turn', function () {
    $claim = bootTurnClaimSqlite();
    seedClaimTurn('T1', ['status' => Turn::STATUS_CANCELLED]);
    seedClaimTurn('T2');

    $claim->recordUsage('T1', [['latency_ms' => 3]]);

    seedClaimTurn('T3', ['status' => Turn::STATUS_RUNNING]);

    expect(claimTurnRow('T1')->only(['status', 'usage']))->toBe(['status' => Turn::STATUS_CANCELLED, 'usage' => [['latency_ms' => 3]]])
        ->and($claim->isRunning('T3'))->toBeTrue()
        ->and($claim->isRunning('T1'))->toBeFalse()
        ->and($claim->isRunning('T2'))->toBeFalse()
        ->and($claim->isRunning('MISSING'))->toBeFalse();
});

it('failUnclaimed only fails turns that are still queued', function () {
    $claim = bootTurnClaimSqlite();
    seedClaimTurn('T-queued');
    seedClaimTurn('T-cancelled', ['status' => Turn::STATUS_CANCELLED]);

    expect($claim->failUnclaimed('T-queued', 'boom'))->toBeTrue()
        ->and($claim->failUnclaimed('T-queued', 'again'))->toBeFalse()
        ->and($claim->failUnclaimed('T-cancelled', 'boom'))->toBeFalse()
        ->and($claim->failUnclaimed('01MISSINGTURN0000000000000', 'boom'))->toBeFalse()
        ->and(claimTurnRow('T-queued')->only(['status', 'error']))->toBe(['status' => Turn::STATUS_FAILED, 'error' => 'boom'])
        ->and(claimTurnRow('T-queued')->finished_at)->not->toBeNull()
        ->and(claimTurnRow('T-cancelled')->status)->toBe(Turn::STATUS_CANCELLED);
});

it('cancels only a queued or running turn', function () {
    $claim = bootTurnClaimSqlite();
    seedClaimTurn('T-queued');
    seedClaimTurn('T-running', ['status' => Turn::STATUS_RUNNING]);
    seedClaimTurn('T-completed', ['status' => Turn::STATUS_COMPLETED]);

    expect($claim->cancel('T-queued'))->toBeTrue()
        ->and($claim->cancel('T-running'))->toBeTrue()
        ->and($claim->cancel('T-queued'))->toBeFalse()
        ->and($claim->cancel('T-completed'))->toBeFalse()
        ->and($claim->cancel('MISSING'))->toBeFalse()
        ->and(claimTurnRow('T-queued')->status)->toBe(Turn::STATUS_CANCELLED)
        ->and(claimTurnRow('T-running')->finished_at)->not->toBeNull()
        ->and(claimTurnRow('T-completed')->status)->toBe(Turn::STATUS_COMPLETED);
});

it('fails stale queued turns by created_at and stale running turns by claimed_at', function () {
    $claim = bootTurnClaimSqlite();
    $now = Carbon::parse('2026-08-07 12:00:00');
    $cutoff = $now->copy()->subMinutes(30);
    seedClaimTurn('Q-old', ['created_at' => $now->copy()->subMinutes(31)]);
    seedClaimTurn('Q-fresh', ['created_at' => $now->copy()->subMinutes(10)]);
    seedClaimTurn('R-old', ['status' => Turn::STATUS_RUNNING, 'created_at' => $now->copy()->subHour(), 'claimed_at' => $now->copy()->subMinutes(31)]);
    seedClaimTurn('R-fresh', ['status' => Turn::STATUS_RUNNING, 'created_at' => $now->copy()->subHour(), 'claimed_at' => $now->copy()->subMinutes(10)]);
    seedClaimTurn('R-unclaimed', ['status' => Turn::STATUS_RUNNING, 'created_at' => $now->copy()->subHour()]);

    expect($claim->failStaleQueued($cutoff, 'reaped: q', $now))->toBe(['Q-old'])
        ->and($claim->failStaleRunning($cutoff, 'reaped: r', $now))->toBe(['R-old'])
        ->and(claimTurnRow('Q-old')->only(['status', 'error']))->toBe(['status' => Turn::STATUS_FAILED, 'error' => 'reaped: q'])
        ->and(claimTurnRow('Q-old')->finished_at?->toDateTimeString())->toBe('2026-08-07 12:00:00')
        ->and(claimTurnRow('R-old')->only(['status', 'error']))->toBe(['status' => Turn::STATUS_FAILED, 'error' => 'reaped: r'])
        ->and(claimTurnRow('Q-fresh')->status)->toBe(Turn::STATUS_QUEUED)
        ->and(claimTurnRow('R-fresh')->status)->toBe(Turn::STATUS_RUNNING)
        ->and(claimTurnRow('R-unclaimed')->status)->toBe(Turn::STATUS_RUNNING);
});

it('skips a turn that left the stale state before its guarded update', function () {
    $claim = bootTurnClaimSqlite();
    $now = Carbon::parse('2026-08-07 12:00:00');
    seedClaimTurn('T1', ['created_at' => $now->copy()->subMinutes(31)]);

    // A worker claims the turn right after the stale select.
    $raced = false;
    Turn::query()->getConnection()->listen(function (QueryExecuted $query) use (&$raced) {
        if ($raced || ! str_starts_with(strtolower($query->sql), 'select')) {
            return;
        }
        $raced = true;
        Turn::query()->where('ulid', 'T1')->update(['status' => Turn::STATUS_RUNNING]);
    });

    expect($claim->failStaleQueued($now->copy()->subMinutes(30), 'reaped: stale queued', $now))->toBe([])
        ->and($raced)->toBeTrue()
        ->and(claimTurnRow('T1')->status)->toBe(Turn::STATUS_RUNNING);
});
