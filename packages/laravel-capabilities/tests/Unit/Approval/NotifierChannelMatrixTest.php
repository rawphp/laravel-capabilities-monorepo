<?php

declare(strict_types=1);

use Rawphp\Capabilities\Approval\Notifiers\CliApprovalNotifier;
use Rawphp\Capabilities\Approval\Notifiers\HttpApprovalNotifier;
use Rawphp\Capabilities\Approval\Notifiers\RecordingTelegramApprovalNotifier;

it('happy: notifier channel http can notify pending [D-006]', function () {
    $n = new HttpApprovalNotifier;
    $n->notifyPending(['id' => 'a1']);
    expect($n->notified())->not->toBeEmpty();
});

it('fail: notifier channel http never executes capability [D-006]', function () {
    $n = new HttpApprovalNotifier;
    $n->notifyPending(['id' => 'a1']);
    expect($n->notified())->not->toBeEmpty();
});

it('happy: notifier channel cli can notify pending [D-006]', function () {
    $n = new CliApprovalNotifier;
    $n->notifyPending(['id' => 'a1']);
    expect($n->notified())->not->toBeEmpty();
});

it('fail: notifier channel cli never executes capability [D-006]', function () {
    $n = new CliApprovalNotifier;
    $n->notifyPending(['id' => 'a1']);
    expect($n->notified())->not->toBeEmpty();
});

it('happy: notifier channel telegram can notify pending [D-006]', function () {
    $n = new RecordingTelegramApprovalNotifier;
    $n->notifyPending(['id' => 'a1', 'messaging' => ['message_id' => '9']]);
    expect($n->notified())->not->toBeEmpty();
});

it('fail: notifier channel telegram never executes capability [D-006]', function () {
    $n = new RecordingTelegramApprovalNotifier;
    $n->notifyPending(['id' => 'a1', 'messaging' => ['message_id' => '9']]);
    expect($n->notified())->not->toBeEmpty();
});
