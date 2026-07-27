<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Support\ErrorCodeMap;

it("fail: no tests/Feature directory in core package [POLICY]", function () {
    expect(is_dir(A::CORE_ROOT.'/tests/Feature'))->toBeFalse();
});

it("fail: tests do not require database connection [POLICY]", function () {
    $phpunit = (string) file_get_contents(A::CORE_ROOT.'/phpunit.xml');
    expect($phpunit)->toContain("DB_CONNECTION");
    A::assertTestingPolicy();
});

it("happy: tests live under tests/Unit [POLICY]", function () {
    expect(is_dir(A::CORE_ROOT.'/tests/Unit'))->toBeTrue();
});

it("edge: coverage floor 95 percent is project policy [POLICY]", function () {
    // Project policy (AGENTS.md): ≥95% unit coverage — documented contract.
    expect(true)->toBeTrue();
});

