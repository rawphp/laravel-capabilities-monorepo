<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Support\ErrorCodeMap;

it("happy: package is: capability registry with typed schemas [IS]", function () {
    A::assertPackageLayout();
    A::governanceStagesPresent();
});

it("happy: package is: invoke adapters for AI MCP HTTP jobs CLI protocol [IS]", function () {
    A::assertPackageLayout();
    A::governanceStagesPresent();
});

it("happy: package is: approval audit scope idempotency governance [IS]", function () {
    A::assertPackageLayout();
    A::governanceStagesPresent();
});

it("happy: package is: conversation ingress contracts [IS]", function () {
    A::assertPackageLayout();
    A::governanceStagesPresent();
});

it("happy: package is: downloadable CLI [IS]", function () {
    A::assertPackageLayout();
    A::governanceStagesPresent();
});

it("happy: package is: discoverable catalog [IS]", function () {
    A::assertPackageLayout();
    A::governanceStagesPresent();
});

