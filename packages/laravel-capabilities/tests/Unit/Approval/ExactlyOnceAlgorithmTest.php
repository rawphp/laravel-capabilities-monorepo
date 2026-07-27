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

it("happy: accept algorithm includes step begin_transaction [D-006]", function () {
    expect(ApprovalStateMachine::acceptIncludesStep('begin_transaction'))->toBeTrue();
});

it("happy: accept algorithm includes step lock_approval_row [D-006]", function () {
    expect(ApprovalStateMachine::acceptIncludesStep('lock_approval_row'))->toBeTrue();
});

it("happy: accept algorithm includes step if_executed_replay [D-006]", function () {
    expect(ApprovalStateMachine::acceptIncludesStep('if_executed_replay'))->toBeTrue();
});

it("happy: accept algorithm includes step if_rejected_conflict [D-006]", function () {
    expect(ApprovalStateMachine::acceptIncludesStep('if_rejected_conflict'))->toBeTrue();
});

it("happy: accept algorithm includes step if_expired_gone [D-006]", function () {
    expect(ApprovalStateMachine::acceptIncludesStep('if_expired_gone'))->toBeTrue();
});

it("happy: accept algorithm includes step if_approved_join_or_in_progress [D-006]", function () {
    expect(ApprovalStateMachine::acceptIncludesStep('if_approved_join_or_in_progress'))->toBeTrue();
});

it("happy: accept algorithm includes step if_pending_shape_a_set_approved [D-006]", function () {
    expect(ApprovalStateMachine::acceptIncludesStep('if_pending_shape_a_set_approved'))->toBeTrue();
});

it("happy: accept algorithm includes step if_pending_shape_b_run_under_lock [D-006]", function () {
    expect(ApprovalStateMachine::acceptIncludesStep('if_pending_shape_b_run_under_lock'))->toBeTrue();
});

it("happy: accept algorithm includes step revalidate [D-006]", function () {
    expect(ApprovalStateMachine::acceptIncludesStep('revalidate'))->toBeTrue();
});

it("happy: accept algorithm includes step authorize_original [D-006]", function () {
    expect(ApprovalStateMachine::acceptIncludesStep('authorize_original'))->toBeTrue();
});

it("happy: accept algorithm includes step run_once [D-006]", function () {
    expect(ApprovalStateMachine::acceptIncludesStep('run_once'))->toBeTrue();
});

it("happy: accept algorithm includes step set_executed_result [D-006]", function () {
    expect(ApprovalStateMachine::acceptIncludesStep('set_executed_result'))->toBeTrue();
});

it("happy: accept algorithm includes step commit [D-006]", function () {
    expect(ApprovalStateMachine::acceptIncludesStep('commit'))->toBeTrue();
});

it("happy: accept algorithm includes step complete_idempotency [D-006]", function () {
    expect(ApprovalStateMachine::acceptIncludesStep('complete_idempotency'))->toBeTrue();
});

