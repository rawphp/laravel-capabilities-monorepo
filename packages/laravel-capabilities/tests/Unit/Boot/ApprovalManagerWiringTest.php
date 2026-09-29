<?php

// L-101 / D-006: one configured ApprovalManager everywhere. The registry pipeline
// requests approvals through the provider's singleton (config, notifiers, audit,
// dispatcher), never through a second default-configured manager.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\Notifiers\HttpApprovalNotifier;
use Rawphp\Capabilities\Approval\Notifiers\RecordingTelegramApprovalNotifier;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\FakeProviderApp;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

function l101Config(array $overrides = []): array
{
    return BootHelpers::config(array_replace_recursive([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
        'audit' => ['driver' => 'memory'],
    ], $overrides));
}

function l101Gated(CapabilityRegistry $registry, string $name = 'l101-gated'): void
{
    Capability::define($name)
        ->description('gated')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->authorize(fn () => true)
        ->run(fn () => new CreateInvoiceResult(invoice_id: 5))
        ->register($registry);
}

it('makeRegistry honours approval.ttl_hours on pipeline-requested rows', function () {
    $registry = ContainerBindings::makeRegistry(l101Config(['approval' => ['ttl_hours' => 4]]));
    l101Gated($registry);

    $result = $registry->invoke('l101-gated', PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    $row = $registry->approvals()->find((string) $result->approvalId());

    $created = new DateTimeImmutable((string) $row['created_at']);
    $expires = new DateTimeImmutable((string) $row['expires_at']);

    expect($result->isApprovalRequired())->toBeTrue()
        ->and($registry->approvals()->ttlHours())->toBe(4)
        ->and($expires->getTimestamp() - $created->getTimestamp())->toBe(4 * 3600);
});

it('makeRegistry keeps approval config when only a store is supplied (REQ-048 path)', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    $store = new InMemoryApprovalStore($clock);

    $registry = ContainerBindings::makeRegistry(l101Config(['approval' => ['ttl_hours' => 72]]), approvalStore: $store);

    expect($registry->approvals()->store())->toBe($store)
        ->and($registry->approvals()->ttlHours())->toBe(72);
});

it('withApprovalManager adopts the manager, its store and config, and re-attaches the registry run path', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    $store = new InMemoryApprovalStore($clock);
    $notifier = new HttpApprovalNotifier;
    $manager = (new ApprovalManager($store, $clock, ['ttl_hours' => 6]))->addNotifier($notifier);

    $registry = (new CapabilityRegistry(clock: $clock))->withApprovalManager($manager);
    l101Gated($registry);

    $pending = $registry->invoke('l101-gated', PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    $approver = PipelineHelpers::userActor(7);
    $approver->tenant_id = 't-1';
    $accepted = $registry->approvals()->accept((string) $pending->approvalId(), $approver);

    expect($registry->approvalStore())->toBe($store)
        ->and($registry->approvals()->ttlHours())->toBe(6)
        ->and($notifier->notified())->toHaveCount(1)
        ->and($notifier->notified()[0]['capability_name'])->toBe('l101-gated')
        ->and($accepted->isOk())->toBeTrue();
});

it('provider: the registry pipeline uses the ApprovalManager singleton configuration', function () {
    $app = FakeProviderApp::registered(l101Config(['approval' => ['ttl_hours' => 4]]));

    /** @var CapabilityRegistry $registry */
    $registry = $app->make(CapabilityRegistry::class);
    /** @var ApprovalManager $singleton */
    $singleton = $app->make(ApprovalManager::class);
    l101Gated($registry);

    $result = $registry->invoke('l101-gated', PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    $row = $singleton->find((string) $result->approvalId());

    $created = new DateTimeImmutable((string) $row['created_at']);
    $expires = new DateTimeImmutable((string) $row['expires_at']);

    expect($registry->approvals()->config())->toBe($singleton->config())
        ->and($registry->approvals()->store())->toBe($singleton->store())
        ->and($expires->getTimestamp() - $created->getTimestamp())->toBe(4 * 3600);
});

it('provider: a container-bound ApprovalNotifier is told about pipeline-requested approvals', function () {
    $recording = new RecordingTelegramApprovalNotifier;
    $app = FakeProviderApp::registered(l101Config(), [ApprovalNotifier::class => $recording]);

    /** @var CapabilityRegistry $registry */
    $registry = $app->make(CapabilityRegistry::class);
    l101Gated($registry);

    $result = $registry->invoke('l101-gated', PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));

    expect($result->isApprovalRequired())->toBeTrue()
        ->and($recording->notified())->toHaveCount(1)
        ->and($recording->notified()[0]['id'])->toBe((string) $result->approvalId());
});

it('provider: tagged approval notifiers are attached once each, alongside the contract binding', function () {
    $tagged = new HttpApprovalNotifier;
    $bound = new RecordingTelegramApprovalNotifier;
    $app = new FakeProviderApp;
    $app->instance(HttpApprovalNotifier::class, $tagged);
    $app->instance(ApprovalNotifier::class, $bound);
    // The same instance bound under the contract and tagged must not notify twice.
    $app->tag([HttpApprovalNotifier::class, ApprovalNotifier::class], CapabilitiesServiceProvider::APPROVAL_NOTIFIER_TAG);
    $app = FakeProviderApp::registered(l101Config(), [], $app);

    /** @var CapabilityRegistry $registry */
    $registry = $app->make(CapabilityRegistry::class);
    l101Gated($registry);

    $registry->invoke('l101-gated', PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));

    expect($tagged->notified())->toHaveCount(1)
        ->and($bound->notified())->toHaveCount(1);
});

it('provider: no bound or tagged notifier means approvals are requested without notification', function () {
    $app = FakeProviderApp::registered(l101Config());

    /** @var CapabilityRegistry $registry */
    $registry = $app->make(CapabilityRegistry::class);
    l101Gated($registry);

    $result = $registry->invoke('l101-gated', PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));

    expect($result->isApprovalRequired())->toBeTrue()
        ->and($app->make(ApprovalManager::class)->find((string) $result->approvalId()))->not->toBeNull();
});
