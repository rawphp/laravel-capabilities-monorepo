<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Tests\Support\SharedFakes;

it('SharedFakes create builds injectable in-memory stores without IO', function () {
    $fakes = SharedFakes::create();

    expect($fakes->approvals)->toBeInstanceOf(ApprovalStore::class)
        ->and($fakes->idempotency)->toBeInstanceOf(IdempotencyStore::class)
        ->and($fakes->clock)->toBeInstanceOf(FixedClock::class);

    $approval = $fakes->approvals->put([
        'capability_name' => 'void-subscription',
        'status' => 'pending',
        'tenant_id' => 't1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '1',
        'original_caller' => 'cli',
    ]);

    $outcome = $fakes->idempotency->put([
        'tenant_id' => 't1',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'void-subscription',
        'idempotency_key' => 'k-1',
        'status' => 'completed',
        'result_json' => ['voided' => true],
    ]);

    expect($fakes->approvals->find($approval['id'])['status'])->toBe('pending')
        ->and($fakes->idempotency->find('t1', 'user', '1', 'void-subscription', 'k-1')['result_json'])
        ->toBe(['voided' => true])
        ->and($outcome['status'])->toBe('completed')
        ->and($fakes->authorizer->authorize('void-subscription', [], null))->toBeTrue();
});
