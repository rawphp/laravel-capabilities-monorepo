<?php

// The `agent` invoke option reaches CapabilityContext::agent() inside run() through the real
// pipeline, and through an approval: the row stores it and the approved run restores it.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

/**
 * @return array{registry: CapabilityRegistry, name: string, seen: stdClass}
 */
function agentContextHarness(): array
{
    $seen = new stdClass;
    $seen->context = null;
    $h = PipelineHelpers::harness(['run' => function ($input, CapabilityContext $context) use ($seen) {
        $seen->context = $context;

        return new CreateInvoiceResult(invoice_id: 1);
    }]);

    return ['registry' => $h['registry'], 'name' => $h['name'], 'seen' => $seen];
}

it('happy: agent option reaches run() context after scope resolution [CTX-001]', function () {
    $h = agentContextHarness();
    $agent = ['conversation_ulid' => 'c1', 'turn_ulid' => 't1'];

    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', [
        'agent' => $agent,
    ]));

    expect($result->isOk())->toBeTrue()
        ->and($h['seen']->context->agent())->toBe($agent)
        // Scope was resolved and applied to the context run() got.
        ->and($h['seen']->context->tenantId())->toBe('t-1');
});

it('edge: invoke without agent option gives run() a null agent context [CTX-001]', function () {
    $h = agentContextHarness();

    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));

    expect($result->isOk())->toBeTrue()
        ->and($h['seen']->context->agent())->toBeNull();
});

it('happy: an approved run gets the agent context the request was held with [D-006]', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    $registry = (new CapabilityRegistry(clock: $clock))
        ->withApprovalManager(new ApprovalManager(new InMemoryApprovalStore($clock), $clock));
    $seen = new stdClass;
    $seen->context = null;
    Capability::define('agent-gated')
        ->description('gated')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->authorize(fn () => true)
        ->run(function ($input, CapabilityContext $context) use ($seen) {
            $seen->context = $context;

            return new CreateInvoiceResult(invoice_id: 5);
        })
        ->register($registry);
    $agent = ['conversation_ulid' => 'c1', 'turn_ulid' => 't1'];

    $pending = $registry->invoke('agent-gated', PipelineHelpers::validInput(), PipelineHelpers::options('agent', [
        'agent' => $agent,
        'needs_approval' => true,
    ]));
    $approver = PipelineHelpers::userActor(7);
    $approver->tenant_id = 't-1';
    $accepted = $registry->approvals()->accept((string) $pending->approvalId(), $approver);

    expect($pending->isApprovalRequired())->toBeTrue()
        ->and($accepted->isOk())->toBeTrue()
        ->and($seen->context->agent())->toBe($agent);
});

it('edge: an approved run of a request held without an agent context gets null [D-006]', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    $registry = (new CapabilityRegistry(clock: $clock))
        ->withApprovalManager(new ApprovalManager(new InMemoryApprovalStore($clock), $clock));
    $seen = new stdClass;
    $seen->context = null;
    Capability::define('http-gated')
        ->description('gated')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->authorize(fn () => true)
        ->run(function ($input, CapabilityContext $context) use ($seen) {
            $seen->context = $context;

            return new CreateInvoiceResult(invoice_id: 5);
        })
        ->register($registry);

    $pending = $registry->invoke('http-gated', PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    $approver = PipelineHelpers::userActor(7);
    $approver->tenant_id = 't-1';
    $accepted = $registry->approvals()->accept((string) $pending->approvalId(), $approver);

    expect($accepted->isOk())->toBeTrue()
        ->and($seen->context->agent())->toBeNull();
});
