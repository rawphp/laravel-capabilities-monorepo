<?php

// D-005: a request refused at the key lookup (conflict: same key, different body; busy: the
// key is still processing) never claimed the key, so its refusal must not be written over the
// row of the request that owns it. Found moving the approval executor's second writer onto
// the pipeline path (L-202).

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;

it('a different-body reuse of a key conflicts without overwriting the owner row, whose retry still replays', function () {
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'owned']);

    $first = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $other = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputB(), $opts);
    $row = $h['store']->find('tenant-1', 'user', '7', $h['name'], 'owned');
    $retry = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);

    expect($other->errorCode())->toBe('conflict')
        ->and($row['status'])->toBe('completed')
        ->and($row['result_json']['data'] ?? null)->toBe($first->data)
        ->and($retry->isOk())->toBeTrue()
        ->and($retry->isReplay())->toBeTrue()
        ->and($retry->data)->toBe($first->data)
        ->and($h['runCount']->value)->toBe(1);
});

it('a retry while the key is processing is busy and leaves the in-flight row processing', function () {
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'in-flight']);
    IdempotencyHelpers::seedRow($h['store'], [
        'idempotency_key' => 'in-flight',
        'status' => 'processing',
        'result_json' => null,
    ]);

    $busy = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $row = $h['store']->find('tenant-1', 'user', '7', $h['name'], 'in-flight');

    expect($busy->errorCode())->toBe('conflict')
        ->and($busy->error['retryable'] ?? null)->toBeTrue()
        ->and($row['status'])->toBe('processing')
        ->and($h['runCount']->value)->toBe(0);
});
