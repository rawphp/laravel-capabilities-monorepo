<?php

// ApprovalGateway: sibling-safe port for accept/reject/find (D-006 / D-007).

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Boot\ArrayContainer;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Contracts\ApprovalGateway;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

it('happy: ApprovalGateway contract exists with find accept reject [D-006]', function () {
    expect(interface_exists(ApprovalGateway::class))->toBeTrue();
    $ref = new ReflectionClass(ApprovalGateway::class);
    $methods = array_map(fn (ReflectionMethod $m) => $m->getName(), $ref->getMethods());
    expect($methods)->toContain('find')
        ->and($methods)->toContain('accept')
        ->and($methods)->toContain('reject');
});

it('happy: ApprovalManager implements ApprovalGateway [D-006]', function () {
    $manager = ApprovalManager::inMemory();
    expect($manager)->toBeInstanceOf(ApprovalGateway::class);
});

it('happy: ApprovalGateway accept reject find behave via manager [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    /** @var ApprovalGateway $gateway */
    $gateway = $h['manager'];
    $id = (string) $h['row']['id'];
    $approver = ApprovalHelpers::requester();

    expect($gateway->find($id))->toBeArray()
        ->and($gateway->find($id)['status'] ?? null)->toBe('pending');

    $rejected = $gateway->reject($id, $approver, 'nope');
    expect($rejected)->toBeInstanceOf(CapabilityResult::class);

    $again = $gateway->find($id);
    expect($again)->not->toBeNull()
        ->and((string) ($again['status'] ?? ''))->toBe('rejected');
});

it('happy: container plan binds ApprovalGateway to ApprovalManager [BOOT-001]', function () {
    expect(ContainerBindings::binds(ApprovalGateway::class))->toBeTrue()
        ->and(ContainerBindings::binds('ApprovalGateway'))->toBeTrue()
        ->and(CapabilitiesServiceProvider::bindingAbstracts())->toContain(ApprovalGateway::class)
        ->and(CapabilitiesServiceProvider::bindingAbstracts())->toContain('ApprovalGateway');

    $plan = ContainerBindings::plan();
    expect($plan[ApprovalGateway::class] ?? null)->toBe(ApprovalManager::class)
        ->and($plan['ApprovalGateway'] ?? null)->toBe(ApprovalGateway::class);

    $c = ArrayContainer::fromPlan();
    expect($c->bound(ApprovalGateway::class))->toBeTrue()
        ->and($c->bound('ApprovalGateway'))->toBeTrue()
        ->and($c->get(ApprovalGateway::class))->toBe(ApprovalManager::class);
});

it('happy: service provider aliases ApprovalGateway to ApprovalManager singleton [BOOT-001]', function () {
    $src = (string) file_get_contents(
        (new ReflectionClass(CapabilitiesServiceProvider::class))->getFileName()
    );
    expect($src)->toContain('ApprovalGateway::class')
        ->and($src)->toContain('alias(ApprovalManager::class, ApprovalGateway::class)')
        ->and($src)->toContain("alias(ApprovalManager::class, 'ApprovalGateway')");
});

it('happy: ApprovalGateway contract has no concrete ApprovalManager use-import [ORI-766]', function () {
    $path = (new ReflectionClass(ApprovalGateway::class))->getFileName();
    expect($path)->not->toBeFalse();
    $src = (string) file_get_contents((string) $path);

    expect($src)->not->toMatch('/^use\s+Rawphp\\\\Capabilities\\\\Approval\\\\ApprovalManager\s*;/m')
        ->and($src)->toMatch('/\\\\Rawphp\\\\Capabilities\\\\Approval\\\\ApprovalManager|never on concrete ApprovalManager/');
});

function gatewayRejectManager(): ApprovalManager
{
    $clock = new FixedClock(new DateTimeImmutable('2026-05-02T00:00:00Z'));

    return ApprovalManager::inMemory($clock)
        ->withConfig([
            'execution' => ApprovalStateMachine::EXECUTION_DEFERRED,
            'ttl_hours' => 1,
            'resume' => [
                'enabled' => true,
                'every_seconds' => 15,
                'grace_seconds' => 5,
                'stuck_after_seconds' => 30,
                'lease_seconds' => 20,
            ],
        ])
        ->withPolicy(new ApprovalPolicy(
            policy: ApprovalPolicy::CUSTOM,
            customChecker: static fn () => true,
        ))
        ->withExecutor(static fn () => CapabilityResult::ok(['x' => 1]));
}

it('reject of an unknown approval id is not_found [D-006]', function () {
    expect(gatewayRejectManager()->reject('missing', (object) ['id' => 'a'])->errorCode())->toBe('not_found');
});

it('reject of a pending approval is rejected or forbidden, and a repeat reject conflicts [D-006]', function () {
    $mgr = gatewayRejectManager();
    $pending = $mgr->request([
        'capability_name' => 'c',
        'tenant_id' => 't1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'input_json' => [],
    ]);

    $rejected = $mgr->reject($pending['id'], (object) ['id' => 'u1'], 'no', ['tenant_id' => 't1']);
    expect($rejected->errorCode())->toBeIn(['rejected', 'forbidden']);

    // second reject on rejected → conflict
    if ($rejected->errorCode() === 'rejected') {
        expect($mgr->reject($pending['id'], (object) ['id' => 'u1'], null, ['tenant_id' => 't1'])->errorCode())
            ->toBe('conflict');
    }
});
