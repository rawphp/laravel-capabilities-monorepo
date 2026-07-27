<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Support\ErrorCodeMap;

it("happy: surfaces are adapters only and call registry invoke [BELIEF-001]", function () {
    A::adaptersAreDumb();
    $h = PipelineHelpers::harness();
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it("fail: no alternate domain mutation API exists in core package source [BELIEF-001]", function () {
    A::noAlternateDomainMutationApi();
});

it("fail: messaging package does not expose alternate run API [D-007]", function () {
    expect(class_exists('Rawphp\\CapabilitiesMessaging\\AlternateRun'))->toBeFalse();
});

it("fail: CLI package contains no domain mutation logic [D-016]", function () {
    A::assertRefuse('domain logic in Go CLI');
});

it("happy: catalog tools and HTTP share same schema source [BELIEF-002]", function () {
    A::catalogAndHttpShareSchemaSource();
});

it("edge: governance authz approval audit actor scope apply on every surface [BELIEF-003]", function () {
    A::governanceStagesPresent();
    foreach (A::CALLERS as $c) {
        A::assertConcernApplies('authorize', $c);
    }
});

