<?php

// D-002 / D-006: the principal that ran the domain executor is a first-class
// column on the approval row (user on accept, SystemActor on resume), not
// something reconstructed from audit or result_json.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalExecutor;
use Rawphp\Capabilities\Approval\ApprovalMetrics;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseApprovalStore;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\SystemActor;

function executorActorFixture(array $executorArgs = []): array
{
    $store = new InMemoryApprovalStore(new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')));
    $row = $store->put([
        'capability_name' => 'create-invoice',
        'status' => ApprovalStateMachine::STATUS_APPROVED,
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
    ]);

    return [$store, $row, new ApprovalExecutor($store, new ApprovalMetrics, ...$executorArgs)];
}

it('new approval rows have no executor actor until execution', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

    foreach ([new InMemoryApprovalStore($clock), new DatabaseApprovalStore(new ArrayTableGateway, $clock)] as $store) {
        $row = $store->put(['capability_name' => 'create-invoice']);

        expect($row)->toHaveKey('executor_actor_type')
            ->and($row['executor_actor_type'])->toBeNull()
            ->and($row)->toHaveKey('executor_actor_id')
            ->and($row['executor_actor_id'])->toBeNull();
    }
});

it('records a user executor on successful execution', function () {
    [$store, $row, $executor] = executorActorFixture([fn () => CapabilityResult::ok(['ran' => true])]);
    $approver = new stdClass;
    $approver->id = 99;

    $executor->execute($row, $approver, via: 'accept');

    $stored = $store->find($row['id']);
    expect($stored['status'])->toBe(ApprovalStateMachine::STATUS_EXECUTED)
        ->and($stored['result_status'])->toBe('ok')
        ->and($stored['executor_actor_type'])->toBe('user')
        ->and($stored['executor_actor_id'])->toBe('99');
});

it('records a SystemActor executor on resume execution', function () {
    [$store, $row, $executor] = executorActorFixture();

    $executor->execute($row, SystemActor::named('scheduler'), via: 'resume');

    $stored = $store->find($row['id']);
    expect($stored['executor_actor_type'])->toBe('system')
        ->and($stored['executor_actor_id'])->toBe('scheduler');
});

it('records the executor when re-validation fails stale', function () {
    [$store, $row, $executor] = executorActorFixture([
        null,
        fn (array $row) => CapabilityResult::failure('failed_stale', 'stale'),
    ]);

    $executor->execute($row, SystemActor::named('scheduler'), via: 'resume');

    $stored = $store->find($row['id']);
    expect($stored['result_status'])->toBe('failed')
        ->and($stored['executor_actor_type'])->toBe('system')
        ->and($stored['executor_actor_id'])->toBe('scheduler');
});

it('records the executor when the original actor is no longer authorized', function () {
    [$store, $row, $executor] = executorActorFixture([
        null,
        null,
        fn (array $row) => false,
    ]);
    $approver = new stdClass;
    $approver->id = 99;

    $executor->execute($row, $approver, via: 'accept');

    $stored = $store->find($row['id']);
    expect($stored['result_status'])->toBe('failed')
        ->and($stored['executor_actor_type'])->toBe('user')
        ->and($stored['executor_actor_id'])->toBe('99');
});

it('lost race replays the winner and keeps the winner executor', function () {
    [$store, $row, $executor] = executorActorFixture([
        function (array $row) use (&$store) {
            // Another worker finishes first under a different principal.
            $store->compareAndUpdate((string) $row['id'], ApprovalStateMachine::STATUS_APPROVED, [
                'status' => ApprovalStateMachine::STATUS_EXECUTED,
                'result_status' => 'ok',
                'result_json' => ['ok' => true, 'data' => ['winner' => true]],
                'executor_actor_type' => 'system',
                'executor_actor_id' => 'scheduler',
            ]);

            return CapabilityResult::ok(['winner' => false]);
        },
    ]);
    $approver = new stdClass;
    $approver->id = 99;

    $executor->execute($row, $approver, via: 'accept');

    $stored = $store->find($row['id']);
    expect($stored['executor_actor_type'])->toBe('system')
        ->and($stored['executor_actor_id'])->toBe('scheduler');
});
