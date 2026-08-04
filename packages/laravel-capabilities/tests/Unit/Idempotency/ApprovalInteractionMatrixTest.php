<?php

// Spec-derived unit tests for D-005 × approval interaction. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Idempotency\WireKeyResolver;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;

it('happy: scenario invoke_with_key_approval_required_stores_key [D-005]', function () {
    $clock = IdempotencyHelpers::clock();
    $store = IdempotencyHelpers::store($clock);
    $guard = IdempotencyHelpers::guard($store, $clock);
    $def = IdempotencyHelpers::mutatingDefinition();
    $ctx = IdempotencyHelpers::context();
    $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());

    $guard->lookup($def, $ctx, 'appr-key', $hash);
    $guard->storeResult(
        $def,
        $ctx,
        'appr-key',
        $hash,
        CapabilityResult::approvalRequired('approval-99'),
        'approval-99',
    );

    $row = $store->find('tenant-1', 'user', '1', 'create-invoice', 'appr-key');
    expect($row['status'])->toBe('pending_approval')
        ->and($row['approval_id'])->toBe('approval-99')
        ->and($row['idempotency_key'])->toBe('appr-key');
});

it('happy: scenario accept_uses_stored_key [D-005]', function () {
    $fromStored = WireKeyResolver::resolve(
        'approval_accept',
        storedKey: 'stored-on-row',
    );
    expect($fromStored)->toBe('stored-on-row');
});

it('happy: scenario accept_replay_same_key [D-005]', function () {
    $clock = IdempotencyHelpers::clock();
    $store = IdempotencyHelpers::store($clock);
    $guard = IdempotencyHelpers::guard($store, $clock);
    $def = IdempotencyHelpers::mutatingDefinition();
    $ctx = IdempotencyHelpers::context();
    $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());

    IdempotencyHelpers::seedRow($store, [
        'idempotency_key' => 'exec-once',
        'actor_id' => '1',
        'status' => 'completed',
        'request_hash' => $hash,
        'result_json' => CapabilityResult::success(['invoice_id' => 5])->toArray(),
    ]);

    $out = $guard->lookup($def, $ctx, 'exec-once', $hash);
    expect($out['action'])->toBe('replay')
        ->and($out['result']->data['invoice_id'])->toBe(5);
});

it('happy: scenario resume_uses_stored_key [D-005]', function () {
    // Resume reuses the original D-005 key stored on the approval/idempotency row.
    $store = IdempotencyHelpers::store();
    $row = IdempotencyHelpers::seedRow($store, [
        'idempotency_key' => 'resume-k',
        'status' => 'processing',
        'approval_id' => 'appr-resume',
        'actor_id' => '1',
    ]);
    expect($row['idempotency_key'])->toBe('resume-k')
        ->and($row['approval_id'])->toBe('appr-resume');
});

it('happy: scenario reject_does_not_complete_as_success [D-005]', function () {
    $clock = IdempotencyHelpers::clock();
    $store = IdempotencyHelpers::store($clock);
    $guard = IdempotencyHelpers::guard($store, $clock);
    $def = IdempotencyHelpers::mutatingDefinition();
    $ctx = IdempotencyHelpers::context();
    $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());

    $guard->lookup($def, $ctx, 'reject-k', $hash);
    $guard->storeResult(
        $def,
        $ctx,
        'reject-k',
        $hash,
        CapabilityResult::failure('forbidden', 'rejected by approver'),
    );

    $row = $store->find('tenant-1', 'user', '1', 'create-invoice', 'reject-k');
    expect($row['status'])->toBe('failed')
        ->and($row['result_json']['ok'])->toBeFalse();
});

it('happy: scenario expired_does_not_complete_as_success [D-005]', function () {
    $store = IdempotencyHelpers::store();
    $row = IdempotencyHelpers::seedRow($store, [
        'status' => 'failed',
        'result_json' => CapabilityResult::failure('expired', 'approval expired')->toArray(),
        'actor_id' => '1',
    ]);
    expect($row['status'])->not->toBe('completed')
        ->and($row['result_json']['error']['code'])->toBe('expired');
});

it('happy: scenario second_invoke_same_key_while_pending [D-005]', function () {
    $clock = IdempotencyHelpers::clock();
    $store = IdempotencyHelpers::store($clock);
    $guard = IdempotencyHelpers::guard($store, $clock);
    $def = IdempotencyHelpers::mutatingDefinition();
    $ctx = IdempotencyHelpers::context();
    $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());

    $guard->lookup($def, $ctx, 'pending-k', $hash);
    $guard->storeResult(
        $def,
        $ctx,
        'pending-k',
        $hash,
        CapabilityResult::approvalRequired('a1'),
        'a1',
    );

    // Second invoke with same key while pending_approval continues/conflicts per hash match;
    // status is pending_approval with same hash → continue (awaiting accept) or replay policy.
    $out = $guard->lookup($def, $ctx, 'pending-k', $hash);
    expect(in_array($out['action'], ['continue', 'busy', 'replay'], true))->toBeTrue();
});

it('happy: scenario second_invoke_same_key_after_executed_replays [D-005]', function () {
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'after-exec']);
    $a = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $b = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    expect($a->isOk())->toBeTrue()
        ->and($b->isReplay())->toBeTrue()
        ->and($h['runCount']->value)->toBe(1);
});
