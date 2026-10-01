<?php

declare(strict_types=1);

use Illuminate\Contracts\Validation\Factory as FactoryContract;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Contracts\Authorizer;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CountingPresenceVerifier;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\FakeProviderApp;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it('fail: a booted registry rejects exists when the row is missing [D-004]', function () {
    $verifier = new CountingPresenceVerifier(['customers' => 0]);
    $ran = 0;
    $registry = bootedRegistry($verifier, $ran);

    $result = $registry->invoke('create-invoice', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->isOk())->toBeFalse()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error['violations'][0]['field'] ?? null)->toBe('customer_id')
        ->and($ran)->toBe(0);
});

it('happy: a booted registry runs the capability when exists matches [D-004]', function () {
    $verifier = new CountingPresenceVerifier(['customers' => 1]);
    $ran = 0;
    $registry = bootedRegistry($verifier, $ran);

    $result = $registry->invoke('create-invoice', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->isOk())->toBeTrue()
        ->and($ran)->toBe(1)
        ->and($verifier->calls)->not->toBeEmpty();
});

it('edge: boot without a validation factory leaves server rules unchecked [D-004]', function () {
    $ran = 0;
    $app = FakeProviderApp::registered(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]), [
        Authorizer::class => StubAuthorizer::allow(),
    ]);
    $registry = $app->make(CapabilityRegistry::class);
    registerInvoice($registry, $ran);

    $result = $registry->invoke('create-invoice', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->isOk())->toBeTrue()
        ->and($ran)->toBe(1);
});

function bootedRegistry(CountingPresenceVerifier $verifier, int &$ran): CapabilityRegistry
{
    $app = FakeProviderApp::registered(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]), [
        Authorizer::class => StubAuthorizer::allow(),
        FactoryContract::class => CountingPresenceVerifier::factory($verifier),
    ]);
    $registry = $app->make(CapabilityRegistry::class);
    registerInvoice($registry, $ran);

    return $registry;
}

function registerInvoice(CapabilityRegistry $registry, int &$ran): void
{
    Capability::define('create-invoice')
        ->description('Create an invoice')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(function () use (&$ran) {
            $ran++;

            return new CreateInvoiceResult(invoice_id: 1);
        })
        ->register($registry);
}
