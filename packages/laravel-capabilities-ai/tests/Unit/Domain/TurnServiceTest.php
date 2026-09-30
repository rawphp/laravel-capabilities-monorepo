<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\TurnService;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryConversationStore;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryTurnClaim;

function turnServiceStore(bool $reset = false): InMemoryConversationStore
{
    static $store = null;
    if ($reset || $store === null) {
        $store = new InMemoryConversationStore;
    }

    return $store;
}

beforeEach(function () {
    turnServiceStore(reset: true);
});

function turnServiceFor(ProgressStore $progress): TurnService
{
    return new TurnService($progress, turnServiceStore(), new InMemoryTurnClaim(turnServiceStore()));
}

function turnServiceConversations(ArrayProgressStore $progress): ConversationService
{
    return new ConversationService(static fn ($j) => null, $progress, store: turnServiceStore());
}

function seedQueuedTurn(): array
{
    $ids = turnServiceConversations(new ArrayProgressStore)->createUserMessage('seed turn', userId: 'u1');

    return [$ids, turnServiceStore()->turn($ids['turn_ulid'])];
}

it('show returns status and conversation_ulid', function () {
    $progress = new ArrayProgressStore;
    [$ids] = seedQueuedTurn();
    $service = turnServiceFor($progress);
    $out = $service->show($ids['turn_ulid'], 'u1');

    expect($out['turn_ulid'])->toBe($ids['turn_ulid'])
        ->and($out['conversation_ulid'])->toBe($ids['conversation_ulid'])
        ->and($out['status'])->toBe(Turn::STATUS_QUEUED);
});

it('show throws when turn missing', function () {
    (turnServiceFor(new ArrayProgressStore))->show('01MISSINGTURNULID0000000', 'u1');
})->throws(ModelNotFoundException::class);

it('cancel queued turn becomes cancelled and writes progress', function () {
    $progress = new ArrayProgressStore;
    [$ids] = seedQueuedTurn();
    $service = turnServiceFor($progress);
    $out = $service->cancel($ids['turn_ulid'], 'u1');

    expect($out['status'])->toBe(Turn::STATUS_CANCELLED)
        ->and(turnServiceStore()->turn($ids['turn_ulid'])->status)->toBe(Turn::STATUS_CANCELLED);

    $events = $progress->since($ids['turn_ulid'], 0);
    $kinds = array_column($events, 'kind');
    expect($kinds)->toContain('status')->toContain('terminal');
});

it('cancel already-cancelled is idempotent', function () {
    $progress = new ArrayProgressStore;
    [$ids] = seedQueuedTurn();
    $service = turnServiceFor($progress);
    $service->cancel($ids['turn_ulid'], 'u1');
    $out = $service->cancel($ids['turn_ulid'], 'u1');

    expect($out['status'])->toBe(Turn::STATUS_CANCELLED);
});

it('cancel completed throws illegal transition', function () {
    $progress = new ArrayProgressStore;
    [$ids, $turn] = seedQueuedTurn();
    $turn->status = Turn::STATUS_COMPLETED;
    (turnServiceFor($progress))->cancel($ids['turn_ulid'], 'u1');
})->throws(RuntimeException::class);

it('events 404s when turn missing', function () {
    (turnServiceFor(new ArrayProgressStore))->events('01MISSINGTURNULID0000000', 'u1');
})->throws(ModelNotFoundException::class);

it('events returns ProgressStore since for existing turn', function () {
    $progress = new ArrayProgressStore;
    [$ids] = seedQueuedTurn();
    $progress->append($ids['turn_ulid'], ['kind' => 'token', 'data' => ['t' => 1]]);
    $events = (turnServiceFor($progress))->events($ids['turn_ulid'], 'u1', 0);

    expect($events)->not->toBeEmpty();
});

it('show, cancel and events hide another owner\'s turn as not found', function (string $method) {
    $progress = new ArrayProgressStore;
    [$ids] = seedQueuedTurn();

    (turnServiceFor($progress))->{$method}($ids['turn_ulid'], 'u2');
})->with(['show', 'cancel', 'events'])->throws(ModelNotFoundException::class);

it('cancel by another owner leaves the turn queued and publishes nothing', function () {
    $progress = new ArrayProgressStore;
    [$ids] = seedQueuedTurn();

    expect(fn () => (turnServiceFor($progress))->cancel($ids['turn_ulid'], 'u2'))->toThrow(ModelNotFoundException::class)
        ->and(turnServiceStore()->turn($ids['turn_ulid'])->status)->toBe(Turn::STATUS_QUEUED)
        ->and($progress->since($ids['turn_ulid'], 0))->toBeEmpty();
});

it('show, cancel and events hide a turn whose conversation has no owner', function (string $method) {
    $progress = new ArrayProgressStore;
    $ids = turnServiceConversations($progress)->createUserMessage('ownerless turn');

    (turnServiceFor($progress))->{$method}($ids['turn_ulid'], 'u1');
})->with(['show', 'cancel', 'events'])->throws(ModelNotFoundException::class);

/**
 * Claim whose cancel CAS loses to a racing writer that moved the turn to $status first.
 */
function racingCancelClaim(string $status): InMemoryTurnClaim
{
    return new class(turnServiceStore(), $status) extends InMemoryTurnClaim
    {
        public function __construct(private readonly InMemoryConversationStore $rows, private readonly string $status)
        {
            parent::__construct($rows);
        }

        public function cancel(string $turnUlid): bool
        {
            $this->rows->turn($turnUlid)->status = $this->status;

            return parent::cancel($turnUlid);
        }
    };
}

it('cancel that loses the race to another cancel is still cancelled', function () {
    [$ids] = seedQueuedTurn();
    $progress = new ArrayProgressStore;

    $out = (new TurnService($progress, turnServiceStore(), racingCancelClaim(Turn::STATUS_CANCELLED)))->cancel($ids['turn_ulid'], 'u1');

    expect($out)->toBe(['turn_ulid' => $ids['turn_ulid'], 'status' => Turn::STATUS_CANCELLED])
        ->and($progress->since($ids['turn_ulid']))->toBe([]);
});

it('cancel that loses the race to a completion throws illegal transition', function () {
    [$ids] = seedQueuedTurn();

    (new TurnService(new ArrayProgressStore, turnServiceStore(), racingCancelClaim(Turn::STATUS_COMPLETED)))->cancel($ids['turn_ulid'], 'u1');
})->throws(RuntimeException::class, 'cannot be cancelled (status=completed)');

it('cancel keeps the turn cancelled and throws when progress append fails', function () {
    [$ids] = seedQueuedTurn();
    $progress = new class implements ProgressStore
    {
        public function append(string $turnUlid, array $event): void
        {
            throw new RuntimeException('redis down');
        }

        public function since(string $turnUlid, int $cursor = 0): array
        {
            return [];
        }
    };

    expect(fn () => turnServiceFor($progress)->cancel($ids['turn_ulid'], 'u1'))
        ->toThrow(RuntimeException::class, 'cancelled in DB but progress append failed: redis down')
        ->and(turnServiceStore()->turn($ids['turn_ulid'])->status)->toBe(Turn::STATUS_CANCELLED);
});
