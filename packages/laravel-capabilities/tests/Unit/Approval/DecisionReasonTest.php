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

it("happy: reject may include decision_reason [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'too high');
    expect($h['store']->find((string) $h['row']['id'])['decision_reason'])->toBe('too high');
});

it("edge: accept may omit decision_reason [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($h['store']->find((string) $h['row']['id'])['decision_reason'])->toBeNull();
});

it("happy: decision_reason stored on row when provided [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'stored');
    expect($h['store']->find((string) $h['row']['id'])['decision_reason'])->toBe('stored');
});

