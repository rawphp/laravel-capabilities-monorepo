<?php

// Spec-derived unit tests for D-005 row shape. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;

it('happy: idempotency row shape includes tenant_id [D-005]', function () {
    $row = IdempotencyHelpers::seedRow(IdempotencyHelpers::store());
    expect($row)->toHaveKey('tenant_id')->and($row['tenant_id'])->toBe('tenant-1');
});

it('happy: idempotency row shape includes actor_type [D-005]', function () {
    $row = IdempotencyHelpers::seedRow(IdempotencyHelpers::store());
    expect($row)->toHaveKey('actor_type')->and($row['actor_type'])->toBe('user');
});

it('happy: idempotency row shape includes actor_id [D-005]', function () {
    $row = IdempotencyHelpers::seedRow(IdempotencyHelpers::store());
    expect($row)->toHaveKey('actor_id')->and($row['actor_id'])->toBe('7');
});

it('happy: idempotency row shape includes capability_name [D-005]', function () {
    $row = IdempotencyHelpers::seedRow(IdempotencyHelpers::store());
    expect($row)->toHaveKey('capability_name')->and($row['capability_name'])->toBe('create-invoice');
});

it('happy: idempotency row shape includes idempotency_key [D-005]', function () {
    $row = IdempotencyHelpers::seedRow(IdempotencyHelpers::store());
    expect($row)->toHaveKey('idempotency_key')->and($row['idempotency_key'])->toBe('key-1');
});

it('happy: idempotency row shape includes request_hash [D-005]', function () {
    $row = IdempotencyHelpers::seedRow(IdempotencyHelpers::store());
    expect($row)->toHaveKey('request_hash')
        ->and($row['request_hash'])->toBe(IdempotencyHelpers::hash(IdempotencyHelpers::inputA()));
});

it('happy: idempotency row shape includes status [D-005]', function () {
    $row = IdempotencyHelpers::seedRow(IdempotencyHelpers::store());
    expect($row)->toHaveKey('status')->and($row['status'])->toBe('completed');
});

it('happy: idempotency row shape includes result_json [D-005]', function () {
    $row = IdempotencyHelpers::seedRow(IdempotencyHelpers::store());
    expect($row)->toHaveKey('result_json')
        ->and($row['result_json'])->toBeArray()
        ->and($row['result_json']['ok'])->toBeTrue();
});

it('happy: idempotency row shape includes approval_id [D-005]', function () {
    $row = IdempotencyHelpers::seedRow(IdempotencyHelpers::store(), ['approval_id' => 'appr-1']);
    expect($row)->toHaveKey('approval_id')->and($row['approval_id'])->toBe('appr-1');
});

it('happy: idempotency row shape includes created_at [D-005]', function () {
    $row = IdempotencyHelpers::seedRow(IdempotencyHelpers::store());
    expect($row)->toHaveKey('created_at')->and($row['created_at'])->not->toBeEmpty();
});

it('happy: idempotency row shape includes expires_at [D-005]', function () {
    $row = IdempotencyHelpers::seedRow(IdempotencyHelpers::store());
    expect($row)->toHaveKey('expires_at')->and($row['expires_at'])->not->toBeEmpty();
    foreach (IdempotencyHelpers::ROW_FIELDS as $field) {
        expect($row)->toHaveKey($field);
    }
});
