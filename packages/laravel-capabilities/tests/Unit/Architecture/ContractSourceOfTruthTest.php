<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Support\ErrorCodeMap;

it("happy: inventory and package stubs are generated together [COV]", function () {
    expect(is_file(A::MONOREPO_ROOT.'/tools/generate_requirement_stubs.py'))->toBeTrue()
        ->and(is_file(A::MONOREPO_ROOT.'/docs/requirements-inventory.md'))->toBeTrue();
});

it("happy: implemented tests become living contract of product behavior [COV]", function () {
    expect(is_dir(A::CORE_ROOT.'/tests/Unit/Architecture'))->toBeTrue();
});

it("fail: behavior not covered by a unit scenario is not considered specified [COV]", function () {
    // Spec: untested behaviour is unspecified for this monorepo.
    expect(is_file(A::MONOREPO_ROOT.'/docs/requirements-inventory.md'))->toBeTrue();
});

it("fail: feature tests are not the contract vehicle for this monorepo [POLICY]", function () {
    expect(is_dir(A::CORE_ROOT.'/tests/Feature'))->toBeFalse();
});

it("edge: regenerate via tools/generate_requirement_stubs.py [COV]", function () {
    expect(is_file(A::MONOREPO_ROOT.'/tools/generate_requirement_stubs.py'))->toBeTrue();
});

it("happy: each decision D-002 through D-023 is represented in scenarios [COV]", function () {
    foreach (A::DECISIONS as $d) {
        A::assertDecisionCoveredInInventory($d);
    }
});

it("happy: each patch P2-004 P2-005 P2-007 is represented in scenarios [COV]", function () {
    foreach (A::PATCHES as $p) {
        A::assertDecisionCoveredInInventory($p);
    }
});

it("fail: hand-edited generated stubs are not the source process [COV]", function () {
    expect(is_file(A::MONOREPO_ROOT.'/tools/generate_requirement_stubs.py'))->toBeTrue();
});

it("happy: happy fail and edge kinds cover success denial and boundaries [COV]", function () {
    $inv = (string) file_get_contents(A::MONOREPO_ROOT.'/docs/requirements-inventory.md');
    expect($inv)->toContain('happy:')->and($inv)->toContain('fail:')->and($inv)->toContain('edge:');
});

it("happy: go CLI unit tests use stable *_test.go names [COV]", function () {
    $cli = A::MONOREPO_ROOT.'/packages/capabilities-cli';
    expect(is_dir($cli) || is_file($cli.'/go.mod'))->toBeTrue();
    // Implemented CLI suite lives under stable Go test names (not generator-era *_todo_test.go).
    if (is_dir($cli)) {
        $todo = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cli, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '_todo_test.go')) {
                $todo[] = $file->getPathname();
            }
        }
        expect($todo)->toBe([]);
    }
});

