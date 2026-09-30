<?php

// L-007 / D-010 §5: bus events reach the host's event dispatcher; the in-memory
// observation stays bounded. Unit-only — a bare Illuminate\Events\Dispatcher, no app.

declare(strict_types=1);

use Illuminate\Events\Dispatcher;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Events\CapabilityApprovalDecided;
use Rawphp\Capabilities\Events\CapabilityApprovalExecuted;
use Rawphp\Capabilities\Events\CapabilityApprovalRequested;
use Rawphp\Capabilities\Events\CapabilityFailed;
use Rawphp\Capabilities\Events\CapabilityInvoked;
use Rawphp\Capabilities\Pipeline\InvokeObservation;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;
use Rawphp\Capabilities\Tests\Fixtures\AuditHelpers;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\FakeProviderApp;

/**
 * @return array{0: Dispatcher, 1: array<string, list<object>>}
 */
function l007Dispatcher(): array
{
    $seen = new ArrayObject;
    $dispatcher = new Dispatcher;
    foreach ([CapabilityInvoked::class, CapabilityFailed::class, CapabilityApprovalRequested::class, CapabilityApprovalDecided::class, CapabilityApprovalExecuted::class] as $class) {
        $dispatcher->listen($class, function (object $event) use ($seen, $class): void {
            $seen[$class] = [...($seen[$class] ?? []), $event];
        });
    }

    return [$dispatcher, $seen];
}

it('dispatches CapabilityInvoked to the host dispatcher exactly once per successful invoke', function () {
    [$dispatcher, $seen] = l007Dispatcher();
    $h = AuditHelpers::harness();
    $h['registry']->withEventDispatcher($dispatcher);

    $result = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('http'));

    expect($result->isOk())->toBeTrue()
        ->and($seen[CapabilityInvoked::class] ?? [])->toHaveCount(1)
        ->and($seen[CapabilityInvoked::class][0]->capability)->toBe($h['name'])
        ->and($seen[CapabilityFailed::class] ?? [])->toBe([]);
});

it('dispatches CapabilityFailed when run() throws', function () {
    [$dispatcher, $seen] = l007Dispatcher();
    $h = AuditHelpers::harness(['run_throws' => 'boom']);
    $h['registry']->withEventDispatcher($dispatcher);

    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('http'));

    expect($seen[CapabilityFailed::class] ?? [])->toHaveCount(1)
        ->and($seen[CapabilityFailed::class][0]->code)->toBe('domain_error');
});

it('dispatches CapabilityApprovalRequested when the pipeline parks an invoke for approval', function () {
    [$dispatcher, $seen] = l007Dispatcher();
    $h = AuditHelpers::harness();
    Capability::define('needs-approval')
        ->description('gated')
        ->surfaces(['http'])
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->needsApproval(fn () => true)
        ->run(fn () => new CreateInvoiceResult(invoice_id: 1))
        ->register($h['registry']);
    $h['registry']->withEventDispatcher($dispatcher);

    $result = $h['registry']->invoke('needs-approval', AuditHelpers::input(), AuditHelpers::options('http'));

    expect($result->errorCode())->toBe('approval_required')
        ->and($seen[CapabilityApprovalRequested::class] ?? [])->toHaveCount(1)
        ->and($seen[CapabilityApprovalRequested::class][0]->approvalId)->toBe($result->error['approval_id'] ?? $result->meta['approval_id'] ?? $seen[CapabilityApprovalRequested::class][0]->approvalId);
});

it('does not dispatch anything when events.enabled is false', function () {
    [$dispatcher, $seen] = l007Dispatcher();
    $h = AuditHelpers::harness(['events' => ['enabled' => false]]);
    $h['registry']->withEventDispatcher($dispatcher);

    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('http'));

    expect((array) $seen)->toBe([]);
});

it('ApprovalManager dispatches decided and executed events through the same dispatcher contract', function () {
    [$dispatcher, $seen] = l007Dispatcher();
    $h = ApprovalHelpers::withPending();
    $manager = $h['manager']->withEventDispatcher($dispatcher);

    $manager->accept((string) $h['row']['id'], ApprovalHelpers::requester());

    expect($seen[CapabilityApprovalDecided::class] ?? [])->toHaveCount(1)
        ->and($seen[CapabilityApprovalDecided::class][0]->decision)->toBe('approved')
        ->and($seen[CapabilityApprovalExecuted::class] ?? [])->toHaveCount(1)
        ->and($seen[CapabilityApprovalExecuted::class][0]->via)->toBe('accept');
});

it('registry withEventDispatcher also reaches its own approval manager decisions', function () {
    [$dispatcher, $seen] = l007Dispatcher();
    $h = AuditHelpers::harness();
    $h['registry']->withEventDispatcher($dispatcher);
    $row = $h['registry']->approvals()->request(ApprovalHelpers::pendingRecord());

    $h['registry']->approvals()->reject((string) $row['id'], ApprovalHelpers::requester(), 'nope');

    expect($seen[CapabilityApprovalDecided::class] ?? [])->toHaveCount(1)
        ->and($seen[CapabilityApprovalDecided::class][0]->decision)->toBe('rejected');
});

it('keeps the in-memory observation bounded across many invokes', function () {
    $h = AuditHelpers::harness(['run_throws' => 'boom']);

    for ($i = 0; $i < InvokeObservation::MAX_RETAINED + 50; $i++) {
        $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('http'));
    }

    expect($h['registry']->failedEvents())->toHaveCount(InvokeObservation::MAX_RETAINED)
        ->and($h['registry']->logs())->toHaveCount(InvokeObservation::MAX_RETAINED)
        ->and($h['registry']->invokedEvents())->toBe([]);
});

it('provider: registry and ApprovalManager use the app events dispatcher when events.enabled', function () {
    [$dispatcher] = l007Dispatcher();
    $config = BootHelpers::config(['approval' => ['store' => 'memory'], 'idempotency' => ['driver' => 'memory'], 'audit' => ['driver' => 'memory']]);

    $on = FakeProviderApp::registered($config, ['events' => $dispatcher]);
    $off = FakeProviderApp::registered(array_replace_recursive($config, ['events' => ['enabled' => false]]), ['events' => $dispatcher]);
    $unbound = FakeProviderApp::registered($config);

    expect($on->make(CapabilityRegistry::class)->eventDispatcher())->toBe($dispatcher)
        ->and($off->make(CapabilityRegistry::class)->eventDispatcher())->toBeNull()
        ->and($unbound->make(CapabilityRegistry::class)->eventDispatcher())->toBeNull();

    [$d2, $seen] = l007Dispatcher();
    $app = FakeProviderApp::registered($config, ['events' => $d2]);
    $manager = $app->make(ApprovalManager::class);
    $row = $manager->request(ApprovalHelpers::pendingRecord());
    $manager->reject((string) $row['id'], ApprovalHelpers::requester(), 'no');

    expect($seen[CapabilityApprovalDecided::class] ?? [])->toHaveCount(1);
});
