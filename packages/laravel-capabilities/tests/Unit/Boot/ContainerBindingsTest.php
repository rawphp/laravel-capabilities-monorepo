<?php

// REQ-014: Container bindings plan (BOOT-001). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Boot\ArrayContainer;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\CapabilitiesServiceProvider;

// REQ-047: prove bare makeRegistry cannot ignore surface/store config.

it('makeRegistry wiring: disabled surfaces and store drivers are applied from config', function () {
    $config = \Rawphp\Capabilities\Tests\Fixtures\BootHelpers::config([
        'surfaces' => \Rawphp\Capabilities\Tests\Fixtures\BootHelpers::surfaces([
            'cli' => false,
            'job' => false,
        ]),
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'database'],
        'audit' => ['mode' => 'strict', 'enabled' => true, 'required' => false, 'driver' => 'database'],
        'transactions' => ['wrap_run' => true],
        'events' => ['enabled' => false],
    ]);

    $registry = ContainerBindings::makeRegistry($config);

    expect($registry->globallyEnabledSurfaces()['cli'])->toBeFalse()
        ->and($registry->globallyEnabledSurfaces()['job'])->toBeFalse()
        ->and($registry->approvalStore())->toBeInstanceOf(\Rawphp\Capabilities\Support\InMemoryApprovalStore::class)
        ->and($registry->idempotencyStore())->toBeInstanceOf(\Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore::class)
        ->and($registry->auditMode())->toBe('strict')
        ->and($registry->scopeResolver())->toBeInstanceOf(\Rawphp\Capabilities\Support\DefaultScopeResolver::class)
        ->and($registry->transactionsWrapRun())->toBeTrue()
        ->and($registry->eventsEnabled())->toBeFalse()
        ->and($registry->clock())->toBeInstanceOf(\Rawphp\Capabilities\Support\SystemClock::class);
});

it("happy: container binds CapabilityRegistry [BOOT-001]", function () {
    expect(ContainerBindings::binds("CapabilityRegistry"))->toBeTrue()
        ->and(CapabilitiesServiceProvider::bindingAbstracts())->toContain("CapabilityRegistry");
    $c = ArrayContainer::fromPlan();
    expect($c->bound("CapabilityRegistry"))->toBeTrue();
});

it("edge: tests can rebind CapabilityRegistry to fake [BOOT-001]", function () {
    $c = ArrayContainer::fromPlan();
    $fake = new class {
        public string $tag = "fake-CapabilityRegistry";
    };
    $c->instance("CapabilityRegistry", $fake);
    expect($c->get("CapabilityRegistry"))->toBe($fake)
        ->and($c->get("CapabilityRegistry")->tag)->toBe("fake-CapabilityRegistry");
});

it("happy: container binds ApprovalManager [BOOT-001]", function () {
    expect(ContainerBindings::binds("ApprovalManager"))->toBeTrue()
        ->and(CapabilitiesServiceProvider::bindingAbstracts())->toContain("ApprovalManager");
    $c = ArrayContainer::fromPlan();
    expect($c->bound("ApprovalManager"))->toBeTrue();
});

it("edge: tests can rebind ApprovalManager to fake [BOOT-001]", function () {
    $c = ArrayContainer::fromPlan();
    $fake = new class {
        public string $tag = "fake-ApprovalManager";
    };
    $c->instance("ApprovalManager", $fake);
    expect($c->get("ApprovalManager"))->toBe($fake)
        ->and($c->get("ApprovalManager")->tag)->toBe("fake-ApprovalManager");
});

it("happy: container binds IdempotencyStore [BOOT-001]", function () {
    expect(ContainerBindings::binds("IdempotencyStore"))->toBeTrue()
        ->and(CapabilitiesServiceProvider::bindingAbstracts())->toContain("IdempotencyStore");
    $c = ArrayContainer::fromPlan();
    expect($c->bound("IdempotencyStore"))->toBeTrue();
});

it("edge: tests can rebind IdempotencyStore to fake [BOOT-001]", function () {
    $c = ArrayContainer::fromPlan();
    $fake = new class {
        public string $tag = "fake-IdempotencyStore";
    };
    $c->instance("IdempotencyStore", $fake);
    expect($c->get("IdempotencyStore"))->toBe($fake)
        ->and($c->get("IdempotencyStore")->tag)->toBe("fake-IdempotencyStore");
});

it("happy: container binds AuditLogger [BOOT-001]", function () {
    expect(ContainerBindings::binds("AuditLogger"))->toBeTrue()
        ->and(CapabilitiesServiceProvider::bindingAbstracts())->toContain("AuditLogger");
    $c = ArrayContainer::fromPlan();
    expect($c->bound("AuditLogger"))->toBeTrue();
});

it("edge: tests can rebind AuditLogger to fake [BOOT-001]", function () {
    $c = ArrayContainer::fromPlan();
    $fake = new class {
        public string $tag = "fake-AuditLogger";
    };
    $c->instance("AuditLogger", $fake);
    expect($c->get("AuditLogger"))->toBe($fake)
        ->and($c->get("AuditLogger")->tag)->toBe("fake-AuditLogger");
});

it("happy: container binds ScopeResolver [BOOT-001]", function () {
    expect(ContainerBindings::binds("ScopeResolver"))->toBeTrue()
        ->and(CapabilitiesServiceProvider::bindingAbstracts())->toContain("ScopeResolver");
    $c = ArrayContainer::fromPlan();
    expect($c->bound("ScopeResolver"))->toBeTrue();
});

it("edge: tests can rebind ScopeResolver to fake [BOOT-001]", function () {
    $c = ArrayContainer::fromPlan();
    $fake = new class {
        public string $tag = "fake-ScopeResolver";
    };
    $c->instance("ScopeResolver", $fake);
    expect($c->get("ScopeResolver"))->toBe($fake)
        ->and($c->get("ScopeResolver")->tag)->toBe("fake-ScopeResolver");
});

it("happy: container binds AiToolAdapter [BOOT-001]", function () {
    expect(ContainerBindings::binds("AiToolAdapter"))->toBeTrue()
        ->and(CapabilitiesServiceProvider::bindingAbstracts())->toContain("AiToolAdapter");
    $c = ArrayContainer::fromPlan();
    expect($c->bound("AiToolAdapter"))->toBeTrue();
});

it("edge: tests can rebind AiToolAdapter to fake [BOOT-001]", function () {
    $c = ArrayContainer::fromPlan();
    $fake = new class {
        public string $tag = "fake-AiToolAdapter";
    };
    $c->instance("AiToolAdapter", $fake);
    expect($c->get("AiToolAdapter"))->toBe($fake)
        ->and($c->get("AiToolAdapter")->tag)->toBe("fake-AiToolAdapter");
});

it("happy: container binds McpToolAdapter [BOOT-001]", function () {
    expect(ContainerBindings::binds("McpToolAdapter"))->toBeTrue()
        ->and(CapabilitiesServiceProvider::bindingAbstracts())->toContain("McpToolAdapter");
    $c = ArrayContainer::fromPlan();
    expect($c->bound("McpToolAdapter"))->toBeTrue();
});

it("edge: tests can rebind McpToolAdapter to fake [BOOT-001]", function () {
    $c = ArrayContainer::fromPlan();
    $fake = new class {
        public string $tag = "fake-McpToolAdapter";
    };
    $c->instance("McpToolAdapter", $fake);
    expect($c->get("McpToolAdapter"))->toBe($fake)
        ->and($c->get("McpToolAdapter")->tag)->toBe("fake-McpToolAdapter");
});

it("happy: container binds Metrics [BOOT-001]", function () {
    expect(ContainerBindings::binds("Metrics"))->toBeTrue()
        ->and(CapabilitiesServiceProvider::bindingAbstracts())->toContain("Metrics");
    $c = ArrayContainer::fromPlan();
    expect($c->bound("Metrics"))->toBeTrue();
});

it("edge: tests can rebind Metrics to fake [BOOT-001]", function () {
    $c = ArrayContainer::fromPlan();
    $fake = new class {
        public string $tag = "fake-Metrics";
    };
    $c->instance("Metrics", $fake);
    expect($c->get("Metrics"))->toBe($fake)
        ->and($c->get("Metrics")->tag)->toBe("fake-Metrics");
});

it("happy: container binds Tracer [BOOT-001]", function () {
    expect(ContainerBindings::binds("Tracer"))->toBeTrue()
        ->and(CapabilitiesServiceProvider::bindingAbstracts())->toContain("Tracer");
    $c = ArrayContainer::fromPlan();
    expect($c->bound("Tracer"))->toBeTrue();
});

it("edge: tests can rebind Tracer to fake [BOOT-001]", function () {
    $c = ArrayContainer::fromPlan();
    $fake = new class {
        public string $tag = "fake-Tracer";
    };
    $c->instance("Tracer", $fake);
    expect($c->get("Tracer"))->toBe($fake)
        ->and($c->get("Tracer")->tag)->toBe("fake-Tracer");
});

