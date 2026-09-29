<?php

declare(strict_types=1);

use Rawphp\Capabilities\Approval\Notifiers\RecordingTelegramApprovalNotifier;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it('edge: approval messaging metadata may include channel [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => ['messaging' => ['channel' => 'telegram', 'chat_id' => 'c1', 'message_id' => 'm1']]]);
    expect($h['row']['messaging'])->toHaveKey('channel');
});

it('edge: approval messaging metadata may include chat_id [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => ['messaging' => ['channel' => 'telegram', 'chat_id' => 'c1', 'message_id' => 'm1']]]);
    expect($h['row']['messaging'])->toHaveKey('chat_id');
});

it('edge: approval messaging metadata may include message_id [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => ['messaging' => ['channel' => 'telegram', 'chat_id' => 'c1', 'message_id' => 'm1']]]);
    expect($h['row']['messaging'])->toHaveKey('message_id');
});

it('happy: telegram notifier can edit message using message_id [D-006]', function () {
    $n = new RecordingTelegramApprovalNotifier;
    $a = ['id' => 'a1', 'messaging' => ['message_id' => '99', 'chat_id' => '1', 'channel' => 'telegram']];
    $n->notifyPending($a);
    $n->editMessage($a, 'expired');
    expect($n->edits())->not->toBeEmpty();
});

it('happy: a pipeline-requested approval row carries the invoke context messaging meta [M-101 / D-006]', function () {
    $h = PipelineHelpers::harness(['surfaces' => ['messaging' => true]]);
    $meta = ['channel' => 'telegram', 'chat_id' => '77', 'message_id' => 9, 'topic_id' => null, 'user_link_id' => '42'];

    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', [
        'needs_approval' => true,
        'messaging' => $meta,
    ]));
    $row = $h['fakes']->approvals->find((string) $result->approvalId());

    expect($result->isApprovalRequired())->toBeTrue()
        ->and($row['messaging'])->toBe($meta);
});

it('edge: an approval requested from HTTP carries no messaging meta [M-101]', function () {
    $h = PipelineHelpers::harness();

    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));

    expect($h['fakes']->approvals->find((string) $result->approvalId())['messaging'])->toBeNull();
});
