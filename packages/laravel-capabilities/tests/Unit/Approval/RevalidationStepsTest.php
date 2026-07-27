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

it("happy: accept revalidation runs step json_schema [D-006]", function () {
    expect(ApprovalStateMachine::revalidationIncludesStep('json_schema'))->toBeTrue();
    $h = ApprovalHelpers::withPending();
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk() || $r->isFailed())->toBeTrue();
});

it("fail: accept fails closed when step json_schema fails [D-006]", function () {
    $h = ApprovalHelpers::withPending(['revalidate_fail' => 'json_schema']);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('executed');
    expect($h['store']->find((string) $h['row']['id'])['result_status'])->toBe('failed');
});

it("fail: accept does not run domain when step json_schema fails [D-006]", function () {
    $h = ApprovalHelpers::withPending(['revalidate_fail' => 'json_schema']);
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: accept revalidation runs step server_only_rules [D-006]", function () {
    expect(ApprovalStateMachine::revalidationIncludesStep('server_only_rules'))->toBeTrue();
    $h = ApprovalHelpers::withPending();
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk() || $r->isFailed())->toBeTrue();
});

it("fail: accept fails closed when step server_only_rules fails [D-006]", function () {
    $h = ApprovalHelpers::withPending(['revalidate_fail' => 'server_only_rules']);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('executed');
    expect($h['store']->find((string) $h['row']['id'])['result_status'])->toBe('failed');
});

it("fail: accept does not run domain when step server_only_rules fails [D-006]", function () {
    $h = ApprovalHelpers::withPending(['revalidate_fail' => 'server_only_rules']);
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: accept revalidation runs step scoped_resolve_each_resource [D-006]", function () {
    expect(ApprovalStateMachine::revalidationIncludesStep('scoped_resolve_each_resource'))->toBeTrue();
    $h = ApprovalHelpers::withPending();
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk() || $r->isFailed())->toBeTrue();
});

it("fail: accept fails closed when step scoped_resolve_each_resource fails [D-006]", function () {
    $h = ApprovalHelpers::withPending(['revalidate_fail' => 'scoped_resolve_each_resource']);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('executed');
    expect($h['store']->find((string) $h['row']['id'])['result_status'])->toBe('failed');
});

it("fail: accept does not run domain when step scoped_resolve_each_resource fails [D-006]", function () {
    $h = ApprovalHelpers::withPending(['revalidate_fail' => 'scoped_resolve_each_resource']);
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: accept revalidation runs step authorize_original_actor [D-006]", function () {
    expect(ApprovalStateMachine::revalidationIncludesStep('authorize_original_actor'))->toBeTrue();
    $h = ApprovalHelpers::withPending();
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk() || $r->isFailed())->toBeTrue();
});

it("fail: accept fails closed when step authorize_original_actor fails [D-006]", function () {
    $h = ApprovalHelpers::withPending(['revalidate_fail' => 'authorize_original_actor']);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    expect($h['store']->find((string) $h['row']['id'])['status'])->toBe('executed');
    expect($h['store']->find((string) $h['row']['id'])['result_status'])->toBe('failed');
});

it("fail: accept does not run domain when step authorize_original_actor fails [D-006]", function () {
    $h = ApprovalHelpers::withPending(['revalidate_fail' => 'authorize_original_actor']);
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

