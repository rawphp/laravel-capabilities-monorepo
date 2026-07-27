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

it("happy: approval_required envelope for caller agent includes approval_id [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', ['needs_approval' => true]));
    expect($r->isApprovalRequired())->toBeTrue()->and($r->approvalId())->not->toBeEmpty();
});

it("happy: approval_required for caller agent does not include domain result data [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', ['needs_approval' => true]));
    expect($r->data)->toBeNull()->and($r->isOk())->toBeFalse();
});

it("happy: approval_required envelope for caller mcp includes approval_id [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', ['needs_approval' => true]));
    expect($r->isApprovalRequired())->toBeTrue()->and($r->approvalId())->not->toBeEmpty();
});

it("happy: approval_required for caller mcp does not include domain result data [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', ['needs_approval' => true]));
    expect($r->data)->toBeNull()->and($r->isOk())->toBeFalse();
});

it("happy: approval_required envelope for caller http includes approval_id [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    expect($r->isApprovalRequired())->toBeTrue()->and($r->approvalId())->not->toBeEmpty();
});

it("happy: approval_required for caller http does not include domain result data [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    expect($r->data)->toBeNull()->and($r->isOk())->toBeFalse();
});

it("happy: approval_required envelope for caller cli includes approval_id [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', ['needs_approval' => true]));
    expect($r->isApprovalRequired())->toBeTrue()->and($r->approvalId())->not->toBeEmpty();
});

it("happy: approval_required for caller cli does not include domain result data [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', ['needs_approval' => true]));
    expect($r->data)->toBeNull()->and($r->isOk())->toBeFalse();
});

it("happy: approval_required envelope for caller job includes approval_id [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', ['needs_approval' => true]));
    expect($r->isApprovalRequired())->toBeTrue()->and($r->approvalId())->not->toBeEmpty();
});

it("happy: approval_required for caller job does not include domain result data [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', ['needs_approval' => true]));
    expect($r->data)->toBeNull()->and($r->isOk())->toBeFalse();
});

