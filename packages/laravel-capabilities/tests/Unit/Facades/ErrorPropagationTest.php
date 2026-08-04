<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;
use Rawphp\Capabilities\Facades\Capability as CapabilityFacade;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it('happy: facade invoke surfaces code validation_failed without swallowing [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    $result = CapabilityFacade::invoke($h['name'], PipelineHelpers::invalidInput(), PipelineHelpers::options('http'));
    expect($result->errorCode())->toBe('validation_failed');
});

it('happy: facade invoke surfaces code unauthenticated without swallowing [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    $result = CapabilityFacade::invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['actor' => null]));
    expect($result->errorCode())->toBe('unauthenticated');
});

it('happy: facade invoke surfaces code forbidden without swallowing [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    $result = CapabilityFacade::invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($result->errorCode())->toBe('forbidden');
});

it('happy: facade invoke surfaces code approval_required without swallowing [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'x']);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    $result = CapabilityFacade::invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    expect($result->errorCode())->toBe('approval_required');
});

it('happy: facade invoke surfaces code domain_error without swallowing [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'run_throws' => 'boom']);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    $result = CapabilityFacade::invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($result->errorCode())->toBe('domain_error');
});

it('happy: facade invoke surfaces code rate_limited without swallowing [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    $result = CapabilityFacade::invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($result->errorCode())->toBe('rate_limited');
});

it('happy: facade invoke surfaces code conflict without swallowing [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    $result = CapabilityFacade::invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'c']));
    expect($result->errorCode())->toBe('conflict');
});

it('happy: facade invoke surfaces code not_found without swallowing [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    $result = CapabilityFacade::invoke('missing', PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($result->errorCode())->toBe('not_found');
});

it('happy: facade invoke surfaces code output_invalid without swallowing [FAC-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'run_output' => ['bad' => true]]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    $result = CapabilityFacade::invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($result->errorCode())->toBe('output_invalid');
});

it('happy: facade invoke surfaces code internal without swallowing [FAC-001]', function () {
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'authorize_cb' => function () {
            throw new RuntimeException('kaboom');
        },
    ]);
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    $result = CapabilityFacade::invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($result->errorCode())->toBe('internal');
});
