<?php

// REQ-010 fleshed unit tests for Catalog/HealthMatrixTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;

it('edge: health reports surface agent status up when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['agent' => 'up'],
        'surfaces' => ['agent' => 'up' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['agent' => true],
    ));
    $h['registry']->withSurfaceHealthOverrides(['agent' => 'up']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['agent']['status'] ?? null)->toBe('up');
});

it('edge: health reports surface agent status disabled_incompatible when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['agent' => 'disabled_incompatible'],
        'surfaces' => ['agent' => 'disabled_incompatible' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['agent' => true],
    ));
    $h['registry']->withSurfaceHealthOverrides(['agent' => 'disabled_incompatible']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['agent']['status'] ?? null)->toBe('disabled_incompatible');
});

it('edge: health reports surface agent status disabled_config when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['agent' => 'disabled_config'],
        'surfaces' => ['agent' => 'disabled_config' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['agent' => false],
    ));
    $h['registry']->withSurfaceHealthOverrides(['agent' => 'disabled_config']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['agent']['status'] ?? null)->toBe('disabled_config');
});

it('edge: health reports surface mcp status up when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['mcp' => 'up'],
        'surfaces' => ['mcp' => 'up' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['mcp' => true],
    ));
    $h['registry']->withSurfaceHealthOverrides(['mcp' => 'up']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['mcp']['status'] ?? null)->toBe('up');
});

it('edge: health reports surface mcp status disabled_incompatible when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['mcp' => 'disabled_incompatible'],
        'surfaces' => ['mcp' => 'disabled_incompatible' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['mcp' => true],
    ));
    $h['registry']->withSurfaceHealthOverrides(['mcp' => 'disabled_incompatible']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['mcp']['status'] ?? null)->toBe('disabled_incompatible');
});

it('edge: health reports surface mcp status disabled_config when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['mcp' => 'disabled_config'],
        'surfaces' => ['mcp' => 'disabled_config' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['mcp' => false],
    ));
    $h['registry']->withSurfaceHealthOverrides(['mcp' => 'disabled_config']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['mcp']['status'] ?? null)->toBe('disabled_config');
});

it('edge: health reports surface http status up when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['http' => 'up'],
        'surfaces' => ['http' => 'up' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['http' => true],
    ));
    $h['registry']->withSurfaceHealthOverrides(['http' => 'up']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['http']['status'] ?? null)->toBe('up');
});

it('edge: health reports surface http status disabled_incompatible when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['http' => 'disabled_incompatible'],
        'surfaces' => ['http' => 'disabled_incompatible' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['http' => true],
    ));
    $h['registry']->withSurfaceHealthOverrides(['http' => 'disabled_incompatible']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['http']['status'] ?? null)->toBe('disabled_incompatible');
});

it('edge: health reports surface http status disabled_config when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['http' => 'disabled_config'],
        'surfaces' => ['http' => 'disabled_config' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['http' => false],
    ));
    $h['registry']->withSurfaceHealthOverrides(['http' => 'disabled_config']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['http']['status'] ?? null)->toBe('disabled_config');
});

it('edge: health reports surface cli status up when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['cli' => 'up'],
        'surfaces' => ['cli' => 'up' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['cli' => true],
    ));
    $h['registry']->withSurfaceHealthOverrides(['cli' => 'up']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['cli']['status'] ?? null)->toBe('up');
});

it('edge: health reports surface cli status disabled_incompatible when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['cli' => 'disabled_incompatible'],
        'surfaces' => ['cli' => 'disabled_incompatible' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['cli' => true],
    ));
    $h['registry']->withSurfaceHealthOverrides(['cli' => 'disabled_incompatible']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['cli']['status'] ?? null)->toBe('disabled_incompatible');
});

it('edge: health reports surface cli status disabled_config when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['cli' => 'disabled_config'],
        'surfaces' => ['cli' => 'disabled_config' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['cli' => false],
    ));
    $h['registry']->withSurfaceHealthOverrides(['cli' => 'disabled_config']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['cli']['status'] ?? null)->toBe('disabled_config');
});

it('edge: health reports surface job status up when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['job' => 'up'],
        'surfaces' => ['job' => 'up' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['job' => true],
    ));
    $h['registry']->withSurfaceHealthOverrides(['job' => 'up']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['job']['status'] ?? null)->toBe('up');
});

it('edge: health reports surface job status disabled_incompatible when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['job' => 'disabled_incompatible'],
        'surfaces' => ['job' => 'disabled_incompatible' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['job' => true],
    ));
    $h['registry']->withSurfaceHealthOverrides(['job' => 'disabled_incompatible']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['job']['status'] ?? null)->toBe('disabled_incompatible');
});

it('edge: health reports surface job status disabled_config when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['job' => 'disabled_config'],
        'surfaces' => ['job' => 'disabled_config' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['job' => false],
    ));
    $h['registry']->withSurfaceHealthOverrides(['job' => 'disabled_config']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['job']['status'] ?? null)->toBe('disabled_config');
});

it('edge: health reports surface artisan status up when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['artisan' => 'up'],
        'surfaces' => ['artisan' => 'up' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['artisan' => true],
    ));
    $h['registry']->withSurfaceHealthOverrides(['artisan' => 'up']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['artisan']['status'] ?? null)->toBe('up');
});

it('edge: health reports surface artisan status disabled_incompatible when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['artisan' => 'disabled_incompatible'],
        'surfaces' => ['artisan' => 'disabled_incompatible' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['artisan' => true],
    ));
    $h['registry']->withSurfaceHealthOverrides(['artisan' => 'disabled_incompatible']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['artisan']['status'] ?? null)->toBe('disabled_incompatible');
});

it('edge: health reports surface artisan status disabled_config when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['artisan' => 'disabled_config'],
        'surfaces' => ['artisan' => 'disabled_config' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['artisan' => false],
    ));
    $h['registry']->withSurfaceHealthOverrides(['artisan' => 'disabled_config']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['artisan']['status'] ?? null)->toBe('disabled_config');
});

it('edge: health reports surface messaging status up when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['messaging' => 'up'],
        'surfaces' => ['messaging' => 'up' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['messaging' => true],
    ));
    $h['registry']->withSurfaceHealthOverrides(['messaging' => 'up']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['messaging']['status'] ?? null)->toBe('up');
});

it('edge: health reports surface messaging status disabled_incompatible when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['messaging' => 'disabled_incompatible'],
        'surfaces' => ['messaging' => 'disabled_incompatible' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['messaging' => true],
    ));
    $h['registry']->withSurfaceHealthOverrides(['messaging' => 'disabled_incompatible']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['messaging']['status'] ?? null)->toBe('disabled_incompatible');
});

it('edge: health reports surface messaging status disabled_config when applicable [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['messaging' => 'disabled_config'],
        'surfaces' => ['messaging' => 'disabled_config' != 'disabled_config'],
    ]);
    // force enabled flag for status matrix
    $h['registry']->withGloballyEnabledSurfaces(array_merge(
        $h['registry']->globallyEnabledSurfaces(),
        ['messaging' => false],
    ));
    $h['registry']->withSurfaceHealthOverrides(['messaging' => 'disabled_config']);
    $report = $h['registry']->catalog()->health();
    expect($report['surfaces']['messaging']['status'] ?? null)->toBe('disabled_config');
});
