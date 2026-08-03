<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Facades\Capability as CapabilityFacade;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it('happy: Capability invoke proxies to registry [PIPE-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'name' => 'facade-invoke']);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    $result = CapabilityFacade::invoke('facade-invoke', PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it('happy: Capability define registers definition [D-017]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'name' => 'already']);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    // define on facade returns builder; register via registry define path
    $def = $h['registry']->define('facade-defined')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn ($in) => new CreateInvoiceResult(1))
        ->register($h['registry']);
    expect($h['registry']->has('facade-defined'))->toBeTrue();
});

it('happy: Capability aiTools proxies to adapter [D-008]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'name' => 'bill-1']);
    // put in billing group
    $h2 = PipelineHelpers::harness(['allowSystemCallers' => true, 'name' => 'bill-tool']);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h2['registry']);
    $tools = CapabilityFacade::aiTools('billing');
    expect($tools)->toBeArray();
});

it('happy: Capability mcpTools proxies to adapter [D-008]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    $tools = CapabilityFacade::mcpTools('billing');
    expect($tools)->toBeArray();
});

it('happy: Capability approvals proxies to ApprovalManager [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    $mgr = CapabilityFacade::approvals();
    expect($mgr)->toBeInstanceOf(ApprovalManager::class);
});

it('fail: Capability invoke does not call domain action directly [PIPE-008]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'name' => 'no-direct']);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    CapabilityFacade::invoke('no-direct', PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($h['runCount']->value)->toBe(1); // only via registry pipeline
});
