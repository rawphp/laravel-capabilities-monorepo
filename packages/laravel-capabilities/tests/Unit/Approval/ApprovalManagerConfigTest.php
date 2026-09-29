<?php

declare(strict_types=1);

// ApprovalManager fluent and config surface.

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\InMemoryAuditWriter;

it('ApprovalManager validates config and exposes its collaborators after fluent with* calls', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00Z'));
    $mgr = ApprovalManager::inMemory($clock);
    $validated = ApprovalManager::validateConfig(['execution' => 'deferred']);
    expect($validated['execution'])->toBe(ApprovalStateMachine::EXECUTION_DEFERRED);

    $clone = $mgr
        ->withPolicy(new ApprovalPolicy)
        ->withConfig(['ttl_seconds' => 120])
        ->withExecutor(static fn () => CapabilityResult::ok(['done' => true]))
        ->withRevalidator(static fn () => true)
        ->withOriginalAuthorizer(static fn () => true)
        ->withAudit(new InMemoryAuditWriter($clock));

    expect($clone->store())->toBeInstanceOf(InMemoryApprovalStore::class)
        ->and($clone->clock())->not->toBeNull()
        ->and($clone->config())->toBeArray()
        ->and($clone->policy())->toBeInstanceOf(ApprovalPolicy::class)
        ->and($clone->metrics())->not->toBeNull()
        ->and($clone->runCount())->toBe(0)
        ->and($clone->events())->toBeArray()
        ->and($clone->machine())->toBeInstanceOf(ApprovalStateMachine::class)
        ->and($clone->executionMode())->toBeString()
        ->and($clone->isDeferred())->toBeBool();

});
