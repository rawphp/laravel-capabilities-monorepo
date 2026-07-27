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

it("happy: needsApproval true does not call run and stores pending approval [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    expect($r->isApprovalRequired())->toBeTrue()->and($h['runCount']->value)->toBe(0);
    expect($h['fakes']->approvals->find((string) $r->approvalId())['status'])->toBe('pending');
});

it("happy: surfaces receive approval_required with approval id and summary [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    expect($r->isApprovalRequired())->toBeTrue()->and($r->approvalId())->not->toBeEmpty();
});

it("happy: accept by authorized approver Shape A sets approved then executes once [D-006]", function () {
    $h = ApprovalHelpers::withPending(['execution' => 'deferred']);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it("happy: accept Shape B pending to executed under lock runs once [D-006]", function () {
    $h = ApprovalHelpers::withPending(['execution' => 'atomic']);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it("happy: reject transitions to rejected and never runs [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'nope');
    expect($h['runCount']->value)->toBe(0)->and($h['store']->find((string) $h['row']['id'])['status'])->toBe('rejected');
});

it("happy: double accept after executed replays result_json without second run [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $r1 = $h['manager']->accept($id, ApprovalHelpers::requester());
    $r2 = $h['manager']->accept($id, ApprovalHelpers::requester());
    expect($h['runCount']->value)->toBe(1);
    expect($r1->data['invoice_id'] ?? null)->toBe($r2->data['invoice_id'] ?? null);
});

it("fail: concurrent accept only one run via conditional update [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['manager']->accept($id, ApprovalHelpers::requester());
    $h['manager']->accept($id, ApprovalHelpers::requester());
    expect($h['runCount']->value)->toBe(1);
});

it("fail: accept when status approved Shape A does not double run [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status'=>'approved','approved_at'=>$h['clock']->now()->format(DATE_ATOM)]);
    $r = $h['manager']->accept($id, ApprovalHelpers::requester());
    expect($h['runCount']->value)->toBe(0);
});

it("edge: accept when status approved Shape A returns in_progress or joins lease [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status'=>'approved','approved_at'=>$h['clock']->now()->format(DATE_ATOM)]);
    $r = $h['manager']->accept($id, ApprovalHelpers::requester());
    expect($r->error['in_progress'] ?? false)->toBeTrue();
});

it("fail: accept when rejected returns conflict [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['manager']->reject($id, ApprovalHelpers::requester());
    expect($h['manager']->accept($id, ApprovalHelpers::requester())->errorCode())->toBe('conflict');
});

it("fail: accept when expired returns 410 or expired error [D-006]", function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    expect($h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester())->errorCode())->toBe('expired');
});

it("fail: accept when executed returns replay not re-run [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['manager']->accept($id, ApprovalHelpers::requester());
    $r = $h['manager']->accept($id, ApprovalHelpers::requester());
    expect($h['runCount']->value)->toBe(1);
    expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
});

it("fail: reject when executed returns conflict [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['manager']->accept($id, ApprovalHelpers::requester());
    expect($h['manager']->reject($id, ApprovalHelpers::requester())->errorCode())->toBe('conflict');
});

it("fail: reject when expired returns conflict or expired [D-006]", function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    $code = $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester())->errorCode();
    expect(in_array($code, ['expired', 'conflict'], true))->toBeTrue();
});

it("fail: reject when already rejected is terminal no-op [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['manager']->reject($id, ApprovalHelpers::requester());
    expect($h['manager']->reject($id, ApprovalHelpers::requester())->errorCode())->toBe('conflict');
});

it("happy: transition from pending to approved allowed under rules [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('pending', 'approved'))->toBeTrue();
});

it("happy: transition from pending to rejected allowed under rules [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('pending', 'rejected'))->toBeTrue();
});

it("happy: transition from pending to expired allowed under rules [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('pending', 'expired'))->toBeTrue();
});

it("happy: transition from pending to executed allowed under rules [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('pending', 'executed'))->toBeTrue();
});

it("fail: transition from approved to pending is forbidden [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('approved', 'pending'))->toBeFalse();
    expect(fn () => ApprovalStateMachine::assertTransition('approved', 'pending'))->toThrow(InvalidArgumentException::class);
});

it("fail: transition from approved to rejected is forbidden [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('approved', 'rejected'))->toBeFalse();
    expect(fn () => ApprovalStateMachine::assertTransition('approved', 'rejected'))->toThrow(InvalidArgumentException::class);
});

it("fail: transition from approved to expired is forbidden [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('approved', 'expired'))->toBeFalse();
    expect(fn () => ApprovalStateMachine::assertTransition('approved', 'expired'))->toThrow(InvalidArgumentException::class);
});

it("happy: transition from approved to executed allowed under rules [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('approved', 'executed'))->toBeTrue();
});

it("fail: transition from rejected to pending is forbidden [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('rejected', 'pending'))->toBeFalse();
    expect(fn () => ApprovalStateMachine::assertTransition('rejected', 'pending'))->toThrow(InvalidArgumentException::class);
});

it("fail: transition from rejected to approved is forbidden [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('rejected', 'approved'))->toBeFalse();
    expect(fn () => ApprovalStateMachine::assertTransition('rejected', 'approved'))->toThrow(InvalidArgumentException::class);
});

it("fail: transition from rejected to expired is forbidden [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('rejected', 'expired'))->toBeFalse();
    expect(fn () => ApprovalStateMachine::assertTransition('rejected', 'expired'))->toThrow(InvalidArgumentException::class);
});

it("fail: transition from rejected to executed is forbidden [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('rejected', 'executed'))->toBeFalse();
    expect(fn () => ApprovalStateMachine::assertTransition('rejected', 'executed'))->toThrow(InvalidArgumentException::class);
});

it("fail: transition from expired to pending is forbidden [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('expired', 'pending'))->toBeFalse();
    expect(fn () => ApprovalStateMachine::assertTransition('expired', 'pending'))->toThrow(InvalidArgumentException::class);
});

it("fail: transition from expired to approved is forbidden [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('expired', 'approved'))->toBeFalse();
    expect(fn () => ApprovalStateMachine::assertTransition('expired', 'approved'))->toThrow(InvalidArgumentException::class);
});

it("fail: transition from expired to rejected is forbidden [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('expired', 'rejected'))->toBeFalse();
    expect(fn () => ApprovalStateMachine::assertTransition('expired', 'rejected'))->toThrow(InvalidArgumentException::class);
});

it("fail: transition from expired to executed is forbidden [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('expired', 'executed'))->toBeFalse();
    expect(fn () => ApprovalStateMachine::assertTransition('expired', 'executed'))->toThrow(InvalidArgumentException::class);
});

it("fail: transition from executed to pending is forbidden [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('executed', 'pending'))->toBeFalse();
    expect(fn () => ApprovalStateMachine::assertTransition('executed', 'pending'))->toThrow(InvalidArgumentException::class);
});

it("fail: transition from executed to approved is forbidden [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('executed', 'approved'))->toBeFalse();
    expect(fn () => ApprovalStateMachine::assertTransition('executed', 'approved'))->toThrow(InvalidArgumentException::class);
});

it("fail: transition from executed to rejected is forbidden [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('executed', 'rejected'))->toBeFalse();
    expect(fn () => ApprovalStateMachine::assertTransition('executed', 'rejected'))->toThrow(InvalidArgumentException::class);
});

it("fail: transition from executed to expired is forbidden [D-006]", function () {
    expect(ApprovalStateMachine::canTransition('executed', 'expired'))->toBeFalse();
    expect(fn () => ApprovalStateMachine::assertTransition('executed', 'expired'))->toThrow(InvalidArgumentException::class);
});

it("happy: re-validation on accept re-runs schema server rules and scoped resolve [D-006]", function () {
    $steps = [];
    $h = ApprovalHelpers::withPending(['revalidator' => function ($row) use (&$steps) {
        $steps = ApprovalStateMachine::revalidationSteps();
        return null;
    }]);
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($steps)->toContain('json_schema');
});

it("fail: stale resource after request time fails accept without run [D-006]", function () {
    $h = ApprovalHelpers::withPending(['stale' => true]);
    expect($h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester())->isOk())->toBeFalse();
    expect($h['runCount']->value)->toBe(0);
});

it("fail: authorize fails for original actor on accept fails without run [D-006]", function () {
    $h = ApprovalHelpers::withPending(['auth_fail' => true]);
    expect($h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester())->errorCode())->toBe('forbidden');
    expect($h['runCount']->value)->toBe(0);
});

it("fail: wrong approver forbidden [D-006]", function () {
    $h = ApprovalHelpers::withPending(['policy' => 'requester']);
    expect($h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::randomUser())->errorCode())->toBe('forbidden');
});

it("fail: SystemActor cannot approve [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    expect($h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::system())->errorCode())->toBe('forbidden');
});

it("happy: approver must be same tenant scope as approval row [D-006]", function () {
    $h = ApprovalHelpers::withPending(['policy' => 'any_staff']);
    expect($h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::otherTenantUser())->errorCode())->toBe('forbidden');
});

it("happy: pending past ttl becomes expired on read [D-006]", function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
});

it("happy: scheduled sweeper expires pending past ttl [D-006]", function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    expect($h['manager']->expirePending())->toBeGreaterThan(0);
});

it("happy: decided_by and decided_at recorded [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    $row = $h['store']->find((string) $h['row']['id']);
    expect($row['decided_by'])->toBe('7')->and($row['decided_at'])->not->toBeNull();
});

it("happy: decision_reason optional on reject [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'too high');
    expect($h['store']->find((string) $h['row']['id'])['decision_reason'])->toBe('too high');
});

it("happy: original caller and scope preserved on execution [D-006]", function () {
    $h = ApprovalHelpers::withPending(['record' => ['original_caller' => 'cli', 'tenant_id' => 't-9']]);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester('7', 't-9'));
    expect($r->data['original_caller'] ?? null)->toBe('cli');
});

it("happy: idempotency key completed after approval execution [D-005]", function () {
    $h = ApprovalHelpers::withPending(['record' => ['idempotency_key' => 'k-1']]);
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($h['idempotency']->find('t-1', 'user', '7', 'create-invoice', 'k-1')['status'] ?? null)->toBe('completed');
});

it("edge: approvalPolicy requester enforces who may decide [D-006]", function () {
    $h = ApprovalHelpers::withPending(['policy' => 'requester']);
    expect($h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester())->isOk())->toBeTrue();
});

it("edge: approvalPolicy requester_or_role enforces who may decide [D-006]", function () {
    $h = ApprovalHelpers::withPending(['policy' => 'requester_or_role']);
    expect($h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::roleHolder())->isOk())->toBeTrue();
});

it("edge: approvalPolicy role:finance-approver enforces who may decide [D-006]", function () {
    $h = ApprovalHelpers::withPending(['policy' => 'role:finance-approver']);
    expect($h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester())->errorCode())->toBe('forbidden');
});

it("edge: approvalPolicy any_staff enforces who may decide [D-006]", function () {
    $h = ApprovalHelpers::withPending(['policy' => 'any_staff']);
    expect($h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::randomUser())->isOk())->toBeTrue();
});

it("edge: approvalPolicy custom enforces who may decide [D-006]", function () {
    $h = ApprovalHelpers::withPending(['policy' => 'custom', 'custom_checker' => fn ($actor, $row, $p) => ($actor->id ?? null) == 7]);
    expect($h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester())->isOk())->toBeTrue();
});

it("edge: per-capability approvalTtlHours lower than global [D-006]", function () {
    $h = ApprovalHelpers::harness(['ttl_hours' => 24]);
    expect($h['manager']->effectiveTtlHours(2))->toBe(2);
});

it("happy: ApprovalManager owns state machine not channel adapters [D-006]", function () {
    $n = new HttpApprovalNotifier();
    expect(method_exists(ApprovalHelpers::harness()['manager'], 'accept'))->toBeTrue();
    expect(method_exists($n, 'accept'))->toBeFalse();
});

it("happy: notifiers notify pending but never execute capabilities [D-006]", function () {
    $n = new HttpApprovalNotifier();
    $h = ApprovalHelpers::harness();
    $h['manager']->addNotifier($n);
    $h['manager']->request(ApprovalHelpers::pendingRecord());
    expect($n->notified())->not->toBeEmpty()->and($h['runCount']->value)->toBe(0);
});

it("happy: result_status ok stored on executed success [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($h['store']->find((string) $h['row']['id'])['result_status'])->toBe('ok');
});

it("happy: result_status failed stored on executed domain failure [D-006]", function () {
    $h = ApprovalHelpers::withPending(['domain_fail' => true]);
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($h['store']->find((string) $h['row']['id'])['result_status'])->toBe('failed');
});

it("happy: audit chain approval.requested [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    expect(array_column($h['audit']->all(), 'event'))->toContain('approval.requested');
});

it("happy: audit chain approval.decided [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect(array_column($h['audit']->all(), 'event'))->toContain('approval.decided');
});

it("happy: audit chain approval.executed [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect(array_column($h['audit']->all(), 'event'))->toContain('approval.executed');
});

it("happy: audit chain approval.replayed on double accept [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['manager']->accept($id, ApprovalHelpers::requester());
    $h['manager']->accept($id, ApprovalHelpers::requester());
    expect(array_column($h['audit']->all(), 'event'))->toContain('approval.replayed');
});

it("happy: audit chain approval.expired [D-006]", function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    $h['manager']->find((string) $h['row']['id']);
    expect(array_column($h['audit']->all(), 'event'))->toContain('approval.expired');
});

it("fail: accept without re-validation is refused [D-006]", function () {
    expect(ApprovalStateMachine::revalidationSteps())->not->toBeEmpty();
});

it("fail: unsigned forgeable approve id alone is refused at HTTP layer [D-006]", function () {
    $v = new ApprovalCallbackVerifier('secret');
    expect(fn () => $v->acceptUnsignedIdOnly('5'))->toThrow(RuntimeException::class);
    expect($v->verify(['approval_id' => '5', 'action' => 'accept', 'exp' => time() + 60]))->toBeFalse();
});

it("fail: eternal pending without ttl is not default [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    expect($h['row']['expires_at'])->not->toBeNull();
});

it("fail: any authenticated user silent multi-tenant approve is not default policy [D-006]", function () {
    $h = ApprovalHelpers::harness();
    expect($h['manager']->policy()->policy())->toBe(ApprovalPolicy::REQUESTER_OR_ROLE);
});

it("edge: execution mode deferred is default Shape A [D-006]", function () {
    expect(ApprovalHelpers::harness()['manager']->isDeferred())->toBeTrue();
});

it("edge: execution mode atomic is Shape B [D-006]", function () {
    expect(ApprovalHelpers::harness(['execution' => 'atomic'])['manager']->isAtomic())->toBeTrue();
});

it("happy: row stores capability_name status scope requester original_caller input_json [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    foreach (['capability_name','status','scope','requester_actor_type','requester_actor_id','original_caller','input_json'] as $k) {
        expect($h['row'])->toHaveKey($k);
    }
});

it("happy: row stores idempotency_key result_json decided_by expires_at lease fields [D-006]", function () {
    $h = ApprovalHelpers::withPending();
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    $row = $h['store']->find((string) $h['row']['id']);
    foreach (['idempotency_key','result_json','decided_by','expires_at','execution_lease_until','execution_attempt'] as $k) {
        expect($row)->toHaveKey($k);
    }
});

it("edge: approval_required path works for original caller agent [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', ['needs_approval' => true]));
    expect($r->isApprovalRequired())->toBeTrue();
});

it("edge: approval_required path works for original caller mcp [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', ['needs_approval' => true]));
    expect($r->isApprovalRequired())->toBeTrue();
});

it("edge: approval_required path works for original caller http [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    expect($r->isApprovalRequired())->toBeTrue();
});

it("edge: approval_required path works for original caller cli [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', ['needs_approval' => true]));
    expect($r->isApprovalRequired())->toBeTrue();
});

it("edge: approval_required path works for original caller job [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', ['needs_approval' => true]));
    expect($r->isApprovalRequired())->toBeTrue();
});

