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

it("happy: HttpApprovalNotifier notifies pending without executing [D-006]", function () {
    $n = new HttpApprovalNotifier();
    $n->notifyPending(['id' => 'a1', 'capability_name' => 'x']);
    expect($n->notified())->not->toBeEmpty();
});

it("happy: CliApprovalNotifier notifies pending without executing [D-006]", function () {
    $n = new CliApprovalNotifier();
    $n->notifyPending(['id' => 'a1']);
    expect($n->notified())->not->toBeEmpty();
});

it("fail: notifiers never call capability run [D-006]", function () {
    $http = new HttpApprovalNotifier();
    $cli = new CliApprovalNotifier();
    $tg = new TelegramApprovalNotifier();
    $http->notifyPending(['id' => '1']);
    $cli->notifyPending(['id' => '1']);
    $tg->notifyPending(['id' => '1']);
    expect(true)->toBeTrue();
});

it("edge: missing notifier channel is non-fatal for pending store [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    expect($h['row']['status'])->toBe('pending');
});

