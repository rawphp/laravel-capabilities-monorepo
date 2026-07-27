<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityResultAssertionException;

it('happy: ok result carries data [RES-001]', function () {
    $result = CapabilityResult::ok(['invoice_id' => 42], [
        'request_id' => 'req-1',
        'capability' => 'create-invoice',
    ]);

    expect($result->ok)->toBeTrue()
        ->and($result->data)->toBe(['invoice_id' => 42])
        ->and($result->error)->toBeNull()
        ->and($result->meta['request_id'] ?? null)->toBe('req-1')
        ->and($result->isOk())->toBeTrue()
        ->and($result->isFailed())->toBeFalse();
});

it('happy: approval_required result carries approval id [RES-001]', function () {
    $result = CapabilityResult::approvalRequired(
        approvalId: 'approval-9',
        message: 'Needs manager approval',
        meta: ['request_id' => 'req-2'],
    );

    expect($result->ok)->toBeFalse()
        ->and($result->isApprovalRequired())->toBeTrue()
        ->and($result->approvalId())->toBe('approval-9')
        ->and($result->errorCode())->toBe('approval_required')
        ->and($result->error['message'] ?? null)->toBe('Needs manager approval');
});

it('happy: failure result carries error envelope fields [RES-001]', function () {
    $result = CapabilityResult::failure(
        code: 'validation_failed',
        message: 'Invalid input',
        extra: [
            'violations' => [
                ['field' => 'customer_id', 'message' => 'must be integer'],
            ],
            'retryable' => false,
        ],
        meta: ['request_id' => 'req-3'],
    );

    expect($result->ok)->toBeFalse()
        ->and($result->isFailed())->toBeTrue()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error['message'] ?? null)->toBe('Invalid input')
        ->and($result->error['violations'][0]['field'] ?? null)->toBe('customer_id')
        ->and($result->error['retryable'] ?? null)->toBeFalse()
        ->and($result->error['request_id'] ?? null)->toBe('req-3');
});

it('happy: replay flag on meta when idempotent replay [D-005]', function () {
    $result = CapabilityResult::ok(['invoice_id' => 1], [
        'idempotent_replay' => true,
        'request_id' => 'req-4',
    ]);

    expect($result->isReplay())->toBeTrue()
        ->and($result->meta['idempotent_replay'] ?? null)->toBeTrue();

    $fresh = CapabilityResult::ok(['invoice_id' => 2]);

    expect($fresh->isReplay())->toBeFalse();
});

it('edge: assertOk assertFailed assertForbidden helpers for tests [RES-001]', function () {
    $ok = CapabilityResult::ok(['x' => 1]);
    expect($ok->assertOk())->toBe($ok);

    $failed = CapabilityResult::failure('domain_error', 'nope');
    expect($failed->assertFailed())->toBe($failed)
        ->and($failed->assertFailed('domain_error'))->toBe($failed);

    $forbidden = CapabilityResult::failure('forbidden', 'denied');
    expect($forbidden->assertForbidden())->toBe($forbidden);

    expect(fn () => $failed->assertOk())->toThrow(CapabilityResultAssertionException::class);
    expect(fn () => $ok->assertFailed())->toThrow(CapabilityResultAssertionException::class);
    expect(fn () => $ok->assertForbidden())->toThrow(CapabilityResultAssertionException::class);
});

it('edge: assertConflict assertExpired helpers for tests [RES-001]', function () {
    $conflict = CapabilityResult::failure('conflict', 'idempotency conflict');
    $expired = CapabilityResult::failure('expired', 'approval expired');

    expect($conflict->assertConflict())->toBe($conflict)
        ->and($expired->assertExpired())->toBe($expired);

    $ok = CapabilityResult::ok([]);
    expect(fn () => $ok->assertConflict())->toThrow(CapabilityResultAssertionException::class);
    expect(fn () => $ok->assertExpired())->toThrow(CapabilityResultAssertionException::class);
});
