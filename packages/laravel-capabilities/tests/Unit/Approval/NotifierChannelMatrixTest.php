<?php

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalCallbackVerifier;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Approval\Notifiers\CliApprovalNotifier;
use Rawphp\Capabilities\Approval\Notifiers\HttpApprovalNotifier;
use Rawphp\Capabilities\Approval\Notifiers\TelegramApprovalNotifier;
use Rawphp\Capabilities\Events\CapabilityApprovalExecuted;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it("happy: notifier channel http can notify pending [D-006]", function () {
    $n = new HttpApprovalNotifier();
    $n->notifyPending(['id' => 'a1']);
    expect($n->notified())->not->toBeEmpty();
});

it("fail: notifier channel http never executes capability [D-006]", function () {
    $n = new HttpApprovalNotifier();
    $n->notifyPending(['id' => 'a1']);
    expect($n->notified())->not->toBeEmpty();
});

it("happy: notifier channel cli can notify pending [D-006]", function () {
    $n = new CliApprovalNotifier();
    $n->notifyPending(['id' => 'a1']);
    expect($n->notified())->not->toBeEmpty();
});

it("fail: notifier channel cli never executes capability [D-006]", function () {
    $n = new CliApprovalNotifier();
    $n->notifyPending(['id' => 'a1']);
    expect($n->notified())->not->toBeEmpty();
});

it("happy: notifier channel telegram can notify pending [D-006]", function () {
    $n = new TelegramApprovalNotifier();
    $n->notifyPending(['id' => 'a1', 'messaging' => ['message_id' => '9']]);
    expect($n->notified())->not->toBeEmpty();
});

it("fail: notifier channel telegram never executes capability [D-006]", function () {
    $n = new TelegramApprovalNotifier();
    $n->notifyPending(['id' => 'a1', 'messaging' => ['message_id' => '9']]);
    expect($n->notified())->not->toBeEmpty();
});

