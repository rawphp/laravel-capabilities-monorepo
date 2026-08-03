<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

it('happy: approval row shape includes id [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('id');
});

it('happy: approval row shape includes capability_name [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('capability_name');
});

it('happy: approval row shape includes status [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('status');
});

it('happy: approval row shape includes scope [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('scope');
});

it('happy: approval row shape includes tenant_id [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('tenant_id');
});

it('happy: approval row shape includes requester_actor_type [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('requester_actor_type');
});

it('happy: approval row shape includes requester_actor_id [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('requester_actor_id');
});

it('happy: approval row shape includes original_caller [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('original_caller');
});

it('happy: approval row shape includes input_json [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('input_json');
});

it('happy: approval row shape includes input_hash [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('input_hash');
});

it('happy: approval row shape includes idempotency_key [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('idempotency_key');
});

it('happy: approval row shape includes result_json [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('result_json');
});

it('happy: approval row shape includes decided_by [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('decided_by');
});

it('happy: approval row shape includes decided_at [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('decided_at');
});

it('happy: approval row shape includes decision_reason [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('decision_reason');
});

it('happy: approval row shape includes expires_at [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('expires_at');
});

it('happy: approval row shape includes execution_lease_until [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('execution_lease_until');
});

it('happy: approval row shape includes execution_attempt [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('execution_attempt');
});

it('happy: approval row shape includes messaging [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('messaging');
});

it('happy: approval row shape includes created_at [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('created_at');
});

it('happy: approval row shape includes updated_at [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '1', 'message_id' => '2'],
    ]]);
    expect($h['row'])->toHaveKey('updated_at');
});
