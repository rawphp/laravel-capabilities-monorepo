<?php

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalStateMachine;

it('happy: resume algorithm includes step select_approved_past_grace_free_lease [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('select_approved_past_grace_free_lease'))->toBeTrue();
});

it('happy: resume algorithm includes step claim_lease_conditional [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('claim_lease_conditional'))->toBeTrue();
});

it('happy: resume algorithm includes step revalidate [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('revalidate'))->toBeTrue();
});

it('happy: resume algorithm includes step scoped_resolve [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('scoped_resolve'))->toBeTrue();
});

it('happy: resume algorithm includes step run_once_or_stale_fail [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('run_once_or_stale_fail'))->toBeTrue();
});

it('happy: resume algorithm includes step set_executed [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('set_executed'))->toBeTrue();
});

it('happy: resume algorithm includes step complete_idempotency [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('complete_idempotency'))->toBeTrue();
});

it('happy: resume algorithm includes step emit_metrics [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('emit_metrics'))->toBeTrue();
});
