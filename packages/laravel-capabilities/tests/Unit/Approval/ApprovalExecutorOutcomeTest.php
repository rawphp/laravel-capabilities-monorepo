<?php

// D-006 / P2-004 / D-019: ApprovalExecutor outcomes — re-validation verdicts, raw domain results,
// stored-result replay, and one metric per stale attempt. Unit-only.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalExecutor;
use Rawphp\Capabilities\Approval\ApprovalMetrics;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\SystemActor;

/**
 * @return array{0: InMemoryApprovalStore, 1: array<string, mixed>, 2: ApprovalExecutor, 3: ApprovalMetrics}
 */
function approvalExecutorFixture(string $status = ApprovalStateMachine::STATUS_APPROVED, ?callable $domain = null, ?callable $revalidator = null): array
{
    $store = new InMemoryApprovalStore(new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')));
    $row = $store->put(['capability_name' => 'create-invoice', 'status' => $status]);
    $metrics = new ApprovalMetrics;

    return [$store, $row, new ApprovalExecutor($store, $metrics, $domain, $revalidator), $metrics];
}

function approvalExecutorApprover(): object
{
    $user = new stdClass;
    $user->id = 5;

    return $user;
}

it('a string re-validation verdict fails the approval with that code and never runs the domain', function () {
    $ran = false;
    [$store, $row, $executor] = approvalExecutorFixture(
        domain: function () use (&$ran) {
            $ran = true;

            return CapabilityResult::ok([]);
        },
        revalidator: static fn (): string => 'customer_archived',
    );

    $result = $executor->execute($row, approvalExecutorApprover(), via: 'accept');

    expect($result->errorCode())->toBe('customer_archived')
        ->and($result->error['message'])->toBe('Re-validation failed: customer_archived')
        ->and($ran)->toBeFalse()
        ->and($store->find($row['id'])['result_status'])->toBe('failed');
});

it('a false re-validation verdict fails stale; true, null, or an ok result lets the domain run', function () {
    [, $row, $stale] = approvalExecutorFixture(revalidator: static fn (): bool => false);
    expect($stale->execute($row, approvalExecutorApprover(), via: 'accept')->errorCode())->toBe('failed_stale');

    foreach ([static fn (): bool => true, static fn () => null, static fn () => CapabilityResult::ok([]), static fn (): int => 1] as $verdict) {
        [$store, $row, $executor] = approvalExecutorFixture(domain: static fn () => CapabilityResult::ok(['ran' => true]), revalidator: $verdict);

        expect($executor->execute($row, approvalExecutorApprover(), via: 'accept')->data)->toBe(['ran' => true])
            ->and($store->find($row['id'])['result_status'])->toBe('ok');
    }
});

it('a stale accept counts once on approvals_accept_total and never on approvals_resume_total [D-019]', function () {
    [, $row, $executor, $metrics] = approvalExecutorFixture(revalidator: static fn (): bool => false);

    $executor->execute($row, approvalExecutorApprover(), via: 'accept');

    expect($metrics->get('approvals_accept_total', ['result' => 'stale']))->toBe(1)
        ->and($metrics->get('approvals_resume_total', ['result' => 'stale']))->toBe(0);
});

it('a stale resume counts once on approvals_resume_total [D-019]', function () {
    [, $row, $executor, $metrics] = approvalExecutorFixture(revalidator: static fn (): bool => false);

    $executor->execute($row, SystemActor::named('approvals-resume'), via: 'resume');

    expect($metrics->get('approvals_resume_total', ['result' => 'stale']))->toBe(1)
        ->and($metrics->get('approvals_accept_total', ['result' => 'stale']))->toBe(0);
});

it('a raw domain return value is wrapped as an ok result and persisted', function () {
    [$store, $row, $executor] = approvalExecutorFixture(domain: static fn (): array => ['invoice_id' => 7]);

    $result = $executor->execute($row, approvalExecutorApprover(), via: 'accept');

    expect($result->isOk())->toBeTrue()
        ->and($result->data)->toBe(['invoice_id' => 7])
        ->and($store->find($row['id'])['result_json']['data'])->toBe(['invoice_id' => 7]);
});

it('an atomic execution whose row was flipped to approved mid-run still lands executed', function () {
    [$store, $row, $executor] = approvalExecutorFixture(domain: static fn () => CapabilityResult::ok(['ran' => true]));

    $result = $executor->execute($row, approvalExecutorApprover(), via: 'accept', fromStatus: ApprovalStateMachine::STATUS_PENDING);

    expect($result->isOk())->toBeTrue()
        ->and($store->find($row['id'])['status'])->toBe(ApprovalStateMachine::STATUS_EXECUTED)
        ->and($store->find($row['id'])['result_status'])->toBe('ok');
});

it('replays a stored failure, defaulting a missing error to domain_error', function () {
    [, , $executor] = approvalExecutorFixture();

    $stored = $executor->resultFromRow(['result_json' => ['ok' => false, 'error' => ['code' => 'card_declined', 'message' => 'Declined'], 'meta' => ['k' => 1]]], replay: true);
    $bare = $executor->resultFromRow(['result_json' => ['ok' => false]], replay: false);

    expect($stored->errorCode())->toBe('card_declined')
        ->and($stored->error['message'])->toBe('Declined')
        ->and($stored->meta)->toMatchArray(['k' => 1, 'idempotent_replay' => true, 'approval_replay' => true])
        ->and($bare->errorCode())->toBe('domain_error')
        ->and($bare->error['message'])->toBe('Stored failure')
        ->and($bare->meta['approval_replay'])->toBeFalse();
});

it('replays a stored non-envelope result as ok data', function () {
    [, , $executor] = approvalExecutorFixture();

    $legacy = $executor->resultFromRow(['result_json' => ['invoice_id' => 3]], replay: true);
    $empty = $executor->resultFromRow([], replay: true);

    expect($legacy->isOk())->toBeTrue()
        ->and($legacy->data)->toBe(['invoice_id' => 3])
        ->and($legacy->meta['approval_replay'])->toBeTrue()
        ->and($empty->isOk())->toBeTrue()
        ->and($empty->data)->toBeNull();
});
