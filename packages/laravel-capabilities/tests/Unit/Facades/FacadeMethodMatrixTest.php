<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Facades\Capability as CapabilityFacade;
use Rawphp\Capabilities\Registry\CapabilityDefinitionBuilder;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it('happy: Capability facade exposes invoke [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    expect(method_exists($h['registry'], 'invoke'))->toBeTrue();
    // exercise via facade when safe
    $r = CapabilityFacade::invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($r)->toBeInstanceOf(CapabilityResult::class);
});

it('happy: Capability facade exposes define [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    expect(method_exists($h['registry'], 'define'))->toBeTrue();
    // exercise via facade when safe
    $b = CapabilityFacade::define('x-facade-m');
    expect($b)->toBeInstanceOf(CapabilityDefinitionBuilder::class);
});

it('happy: Capability facade exposes aiTools [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    expect(method_exists($h['registry'], 'aiTools'))->toBeTrue();
    // exercise via facade when safe
    expect(CapabilityFacade::aiTools('billing'))->toBeArray();
});

it('happy: Capability facade exposes aiMetaTools [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    expect(method_exists($h['registry'], 'aiMetaTools'))->toBeTrue();
    // exercise via facade when safe
    expect(CapabilityFacade::aiMetaTools('billing'))->toBeArray();
});

it('happy: Capability facade exposes mcpTools [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    expect(method_exists($h['registry'], 'mcpTools'))->toBeTrue();
    // exercise via facade when safe
    expect(CapabilityFacade::mcpTools('billing'))->toBeArray();
});

it('happy: Capability facade exposes mcpMetaTools [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    expect(method_exists($h['registry'], 'mcpMetaTools'))->toBeTrue();
    // exercise via facade when safe
    expect(CapabilityFacade::mcpMetaTools('billing'))->toBeArray();
});

it('happy: Capability facade exposes approvals [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    expect(method_exists($h['registry'], 'approvals'))->toBeTrue();
    // exercise via facade when safe
    expect(CapabilityFacade::approvals())->toBeInstanceOf(ApprovalManager::class);
});

it('happy: Capability facade exposes audit [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    expect(method_exists($h['registry'], 'audit'))->toBeTrue();
    // exercise via facade when safe
    expect(CapabilityFacade::audit())->not->toBeNull();
});

it('happy: Capability facade exposes fake [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    expect(method_exists($h['registry'], 'fake'))->toBeTrue();
    // exercise via facade when safe
    expect(CapabilityFacade::fake())->toBeInstanceOf(CapabilityRegistry::class);
});

it('happy: Capability facade exposes assertParity [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    expect(method_exists($h['registry'], 'assertParity'))->toBeTrue();
    // exercise via facade with D-020 options shape
    expect(CapabilityFacade::assertParity($h['name'], [
        'input' => PipelineHelpers::validInput(),
        'surfaces' => ['http', 'cli'],
        'actor' => PipelineHelpers::userActor(),
    ]))->toBeTrue();
});

it('happy: Capability facade exposes assertSchemaSnapshot [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    expect(method_exists($h['registry'], 'assertSchemaSnapshot'))->toBeTrue();
    // exercise via facade when safe
    expect(CapabilityFacade::assertSchemaSnapshot($h['name']))->toBeTrue();
});

it('happy: Capability facade exposes assertCannotInvokeAcrossTenant [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    expect(method_exists($h['registry'], 'assertCannotInvokeAcrossTenant'))->toBeTrue();
    // exercise via facade when safe
    expect(CapabilityFacade::assertCannotInvokeAcrossTenant())->toBeTrue();
});
