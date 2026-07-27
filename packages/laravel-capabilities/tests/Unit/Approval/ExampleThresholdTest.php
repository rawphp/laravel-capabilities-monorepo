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

it("happy: example amount_cents >= 100000 may require approval for agent mcp cli [D-006]", function () {
    foreach (['agent', 'mcp', 'cli'] as $caller) {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], ['customer_id' => 1, 'amount_cents' => 100000, 'currency' => 'USD'], PipelineHelpers::options($caller, [
            'needs_approval_callback' => function ($in, $ctx) {
                $amount = is_object($in) ? $in->amount_cents : ($in['amount_cents'] ?? 0);
                return $amount >= 100000 && in_array($ctx->caller(), ['agent', 'mcp', 'cli'], true);
            },
        ]));
        expect($r->isApprovalRequired())->toBeTrue();
    }
});

it("edge: example http staff path may not require approval for same amount [D-006]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], ['customer_id' => 1, 'amount_cents' => 100000, 'currency' => 'USD'], PipelineHelpers::options('http', [
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() !== 'http',
    ]));
    expect($r->isOk())->toBeTrue();
});

it("fail: approval branch uses derived caller not header in example [D-022]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], ['customer_id' => 1, 'amount_cents' => 100000, 'currency' => 'USD'], PipelineHelpers::options('http', [
        'headers' => ['X-Capability-Caller' => 'agent'],
        'needs_approval_callback' => fn ($in, $ctx) => in_array($ctx->caller(), ['agent', 'mcp', 'cli'], true),
    ]));
    expect($r->isApprovalRequired())->toBeFalse();
});

