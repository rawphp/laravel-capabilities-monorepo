<?php

declare(strict_types=1);

use Rawphp\Capabilities\Approval\Notifiers\RecordingTelegramApprovalNotifier;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

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
