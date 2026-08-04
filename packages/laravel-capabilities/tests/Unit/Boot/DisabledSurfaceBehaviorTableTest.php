<?php

// REQ-014: Disabled surface behavior table (SURF-003). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Boot\SurfaceRegistrar;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

it('happy: when agent disabled: no laravel/ai tools registered [SURF-003]', function () {
    $surfaces = BootHelpers::surfaces(['agent' => false]);
    expect(SurfaceRegistrar::artifacts('agent', $surfaces))->toBeEmpty();
});

it('fail: when agent disabled: no half registration [SURF-003]', function () {
    $surfaces = BootHelpers::surfaces(['agent' => false]);
    expect(SurfaceRegistrar::isHalfRegistered('agent', $surfaces))->toBeFalse()
        ->and(SurfaceRegistrar::artifacts('agent', $surfaces))->toBeEmpty();
});

it('happy: when mcp disabled: no MCP tools catalog wiring [SURF-003]', function () {
    $surfaces = BootHelpers::surfaces(['mcp' => false]);
    expect(SurfaceRegistrar::artifacts('mcp', $surfaces))->toBeEmpty();
});

it('fail: when mcp disabled: no half registration [SURF-003]', function () {
    $surfaces = BootHelpers::surfaces(['mcp' => false]);
    expect(SurfaceRegistrar::isHalfRegistered('mcp', $surfaces))->toBeFalse()
        ->and(SurfaceRegistrar::artifacts('mcp', $surfaces))->toBeEmpty();
});

it('happy: when http disabled: no capability HTTP routes [SURF-003]', function () {
    $surfaces = BootHelpers::surfaces(['http' => false]);
    expect(SurfaceRegistrar::artifacts('http', $surfaces))->toBeEmpty();
});

it('fail: when http disabled: no half registration [SURF-003]', function () {
    $surfaces = BootHelpers::surfaces(['http' => false]);
    expect(SurfaceRegistrar::isHalfRegistered('http', $surfaces))->toBeFalse()
        ->and(SurfaceRegistrar::artifacts('http', $surfaces))->toBeEmpty();
});

it('happy: when cli disabled: device-code CLI auth helpers off [SURF-003]', function () {
    $surfaces = BootHelpers::surfaces(['cli' => false]);
    expect(SurfaceRegistrar::artifacts('cli', $surfaces))->toBeEmpty();
});

it('fail: when cli disabled: no half registration [SURF-003]', function () {
    $surfaces = BootHelpers::surfaces(['cli' => false]);
    expect(SurfaceRegistrar::isHalfRegistered('cli', $surfaces))->toBeFalse()
        ->and(SurfaceRegistrar::artifacts('cli', $surfaces))->toBeEmpty();
});

it('happy: when job disabled: RunCapability helpers not registered [SURF-003]', function () {
    $surfaces = BootHelpers::surfaces(['job' => false]);
    expect(SurfaceRegistrar::artifacts('job', $surfaces))->toBeEmpty();
});

it('fail: when job disabled: no half registration [SURF-003]', function () {
    $surfaces = BootHelpers::surfaces(['job' => false]);
    expect(SurfaceRegistrar::isHalfRegistered('job', $surfaces))->toBeFalse()
        ->and(SurfaceRegistrar::artifacts('job', $surfaces))->toBeEmpty();
});

it('happy: when artisan disabled: no capability artisan commands [SURF-003]', function () {
    $surfaces = BootHelpers::surfaces(['artisan' => false]);
    expect(SurfaceRegistrar::artifacts('artisan', $surfaces))->toBeEmpty();
});

it('fail: when artisan disabled: no half registration [SURF-003]', function () {
    $surfaces = BootHelpers::surfaces(['artisan' => false]);
    expect(SurfaceRegistrar::isHalfRegistered('artisan', $surfaces))->toBeFalse()
        ->and(SurfaceRegistrar::artifacts('artisan', $surfaces))->toBeEmpty();
});

it('happy: when messaging disabled: core does not register chat routes [SURF-003]', function () {
    $surfaces = BootHelpers::surfaces(['messaging' => false]);
    expect(SurfaceRegistrar::artifacts('messaging', $surfaces))->toBeEmpty();
});

it('fail: when messaging disabled: no half registration [SURF-003]', function () {
    $surfaces = BootHelpers::surfaces(['messaging' => false]);
    expect(SurfaceRegistrar::isHalfRegistered('messaging', $surfaces))->toBeFalse()
        ->and(SurfaceRegistrar::artifacts('messaging', $surfaces))->toBeEmpty();
});
