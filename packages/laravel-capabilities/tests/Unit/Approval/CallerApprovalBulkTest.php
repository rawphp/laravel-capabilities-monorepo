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

it("happy: approval needs_true_pending for original caller agent [D-006]", function () {
    $caller = 'agent';
    if ('needs_true_pending' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_true_pending' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_true_pending' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_true_pending' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_true_pending' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('needs_true_pending' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval needs_false_runs for original caller agent [D-006]", function () {
    $caller = 'agent';
    if ('needs_false_runs' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_false_runs' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_false_runs' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_false_runs' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_false_runs' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('needs_false_runs' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval accept_ok for original caller agent [D-006]", function () {
    $caller = 'agent';
    if ('accept_ok' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_ok' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_ok' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_ok' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_ok' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('accept_ok' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("fail: approval accept_stale for original caller agent [D-006]", function () {
    $caller = 'agent';
    if ('accept_stale' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_stale' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_stale' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_stale' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_stale' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('accept_stale' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval reject for original caller agent [D-006]", function () {
    $caller = 'agent';
    if ('reject' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('reject' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('reject' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('reject' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('reject' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('reject' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval expire for original caller agent [D-006]", function () {
    $caller = 'agent';
    if ('expire' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('expire' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('expire' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('expire' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('expire' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('expire' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval double_accept_replay for original caller agent [D-006]", function () {
    $caller = 'agent';
    if ('double_accept_replay' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('double_accept_replay' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('double_accept_replay' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('double_accept_replay' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('double_accept_replay' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('double_accept_replay' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval needs_true_pending for original caller mcp [D-006]", function () {
    $caller = 'mcp';
    if ('needs_true_pending' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_true_pending' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_true_pending' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_true_pending' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_true_pending' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('needs_true_pending' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval needs_false_runs for original caller mcp [D-006]", function () {
    $caller = 'mcp';
    if ('needs_false_runs' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_false_runs' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_false_runs' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_false_runs' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_false_runs' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('needs_false_runs' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval accept_ok for original caller mcp [D-006]", function () {
    $caller = 'mcp';
    if ('accept_ok' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_ok' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_ok' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_ok' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_ok' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('accept_ok' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("fail: approval accept_stale for original caller mcp [D-006]", function () {
    $caller = 'mcp';
    if ('accept_stale' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_stale' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_stale' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_stale' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_stale' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('accept_stale' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval reject for original caller mcp [D-006]", function () {
    $caller = 'mcp';
    if ('reject' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('reject' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('reject' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('reject' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('reject' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('reject' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval expire for original caller mcp [D-006]", function () {
    $caller = 'mcp';
    if ('expire' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('expire' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('expire' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('expire' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('expire' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('expire' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval double_accept_replay for original caller mcp [D-006]", function () {
    $caller = 'mcp';
    if ('double_accept_replay' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('double_accept_replay' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('double_accept_replay' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('double_accept_replay' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('double_accept_replay' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('double_accept_replay' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval needs_true_pending for original caller http [D-006]", function () {
    $caller = 'http';
    if ('needs_true_pending' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_true_pending' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_true_pending' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_true_pending' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_true_pending' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('needs_true_pending' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval needs_false_runs for original caller http [D-006]", function () {
    $caller = 'http';
    if ('needs_false_runs' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_false_runs' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_false_runs' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_false_runs' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_false_runs' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('needs_false_runs' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval accept_ok for original caller http [D-006]", function () {
    $caller = 'http';
    if ('accept_ok' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_ok' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_ok' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_ok' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_ok' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('accept_ok' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("fail: approval accept_stale for original caller http [D-006]", function () {
    $caller = 'http';
    if ('accept_stale' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_stale' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_stale' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_stale' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_stale' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('accept_stale' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval reject for original caller http [D-006]", function () {
    $caller = 'http';
    if ('reject' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('reject' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('reject' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('reject' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('reject' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('reject' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval expire for original caller http [D-006]", function () {
    $caller = 'http';
    if ('expire' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('expire' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('expire' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('expire' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('expire' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('expire' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval double_accept_replay for original caller http [D-006]", function () {
    $caller = 'http';
    if ('double_accept_replay' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('double_accept_replay' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('double_accept_replay' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('double_accept_replay' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('double_accept_replay' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('double_accept_replay' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval needs_true_pending for original caller cli [D-006]", function () {
    $caller = 'cli';
    if ('needs_true_pending' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_true_pending' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_true_pending' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_true_pending' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_true_pending' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('needs_true_pending' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval needs_false_runs for original caller cli [D-006]", function () {
    $caller = 'cli';
    if ('needs_false_runs' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_false_runs' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_false_runs' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_false_runs' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_false_runs' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('needs_false_runs' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval accept_ok for original caller cli [D-006]", function () {
    $caller = 'cli';
    if ('accept_ok' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_ok' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_ok' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_ok' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_ok' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('accept_ok' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("fail: approval accept_stale for original caller cli [D-006]", function () {
    $caller = 'cli';
    if ('accept_stale' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_stale' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_stale' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_stale' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_stale' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('accept_stale' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval reject for original caller cli [D-006]", function () {
    $caller = 'cli';
    if ('reject' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('reject' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('reject' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('reject' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('reject' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('reject' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval expire for original caller cli [D-006]", function () {
    $caller = 'cli';
    if ('expire' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('expire' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('expire' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('expire' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('expire' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('expire' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval double_accept_replay for original caller cli [D-006]", function () {
    $caller = 'cli';
    if ('double_accept_replay' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('double_accept_replay' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('double_accept_replay' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('double_accept_replay' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('double_accept_replay' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('double_accept_replay' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval needs_true_pending for original caller job [D-006]", function () {
    $caller = 'job';
    if ('needs_true_pending' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_true_pending' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_true_pending' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_true_pending' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_true_pending' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('needs_true_pending' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval needs_false_runs for original caller job [D-006]", function () {
    $caller = 'job';
    if ('needs_false_runs' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_false_runs' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_false_runs' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('needs_false_runs' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('needs_false_runs' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('needs_false_runs' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval accept_ok for original caller job [D-006]", function () {
    $caller = 'job';
    if ('accept_ok' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_ok' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_ok' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_ok' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_ok' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('accept_ok' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("fail: approval accept_stale for original caller job [D-006]", function () {
    $caller = 'job';
    if ('accept_stale' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_stale' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_stale' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('accept_stale' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('accept_stale' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('accept_stale' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval reject for original caller job [D-006]", function () {
    $caller = 'job';
    if ('reject' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('reject' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('reject' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('reject' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('reject' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('reject' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval expire for original caller job [D-006]", function () {
    $caller = 'job';
    if ('expire' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('expire' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('expire' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('expire' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('expire' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('expire' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

it("happy: approval double_accept_replay for original caller job [D-006]", function () {
    $caller = 'job';
    if ('double_accept_replay' === 'needs_true_pending') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
        expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    } elseif ('double_accept_replay' === 'needs_false_runs') {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => false]));
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('double_accept_replay' === 'accept_ok') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } elseif ('double_accept_replay' === 'accept_stale') {
        $h = ApprovalHelpers::withPending(['stale' => true, 'record' => ['original_caller' => $caller]]);
        $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    } elseif ('double_accept_replay' === 'reject') {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no');
        expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
    } elseif ('double_accept_replay' === 'expire') {
        $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'record' => ['original_caller' => $caller]]);
        ApprovalHelpers::advanceHours($h['clock'], 2);
        expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
    } else {
        $h = ApprovalHelpers::withPending(['record' => ['original_caller' => $caller]]);
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $r2 = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        expect($h['runCount']->value)->toBe(1);
        expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
    }
});

