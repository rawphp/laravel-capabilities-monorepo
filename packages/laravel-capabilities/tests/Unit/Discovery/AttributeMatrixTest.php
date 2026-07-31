<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;

$surfaceSets = [
    ['agent'],
    ['mcp'],
    ['http'],
    ['cli'],
    ['job'],
    ['artisan'],
    ['agent', 'mcp'],
    ['http', 'cli'],
    ['agent', 'http', 'cli'],
    ['agent', 'mcp', 'http', 'cli', 'job'],
];

foreach ($surfaceSets as $surfaces) {
    $label = implode(',', $surfaces);

    it("happy: definition surfaces [{$label}] stored [D-017]", function () use ($surfaces) {
        $registry = DiscoveryHelpers::registry();
        $def = Capability::define('surf-'.md5(implode('-', $surfaces)))
            ->readOnly(true)
            ->surfaces($surfaces)
            ->register($registry);

        expect($def->surfaces)->toBe($surfaces);
    });

    it("edge: effective exposure for [{$label}] intersects globals [SURF-001]", function () use ($surfaces) {
        $globals = [
            'agent' => true,
            'mcp' => false,
            'http' => true,
            'cli' => true,
            'job' => false,
            'artisan' => true,
            'messaging' => false,
        ];
        $registry = DiscoveryHelpers::registry($globals);
        $def = Capability::define('eff-'.md5(implode('-', $surfaces)))
            ->readOnly(true)
            ->surfaces($surfaces)
            ->register($registry);

        $effective = $def->effectiveSurfaces($registry->globallyEnabledSurfaces());
        foreach ($effective as $s) {
            expect($globals[$s] ?? false)->toBeTrue();
            expect($surfaces)->toContain($s);
        }
        foreach ($surfaces as $s) {
            if (($globals[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            } else {
                expect($effective)->not->toContain($s);
            }
        }
    });
}

it('edge: definition surfaces [empty] yields no exposure [SURF-001]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = Capability::define('empty-surfaces')
        ->readOnly(true)
        ->surfaces([])
        ->register($registry);

    expect($def->hasEffectiveExposure($registry->globallyEnabledSurfaces()))->toBeFalse();
});

foreach (['optional', 'required', 'none', false] as $flag) {
    // Inventory/generator uses Python repr: strings as 'optional', bool as False (not PHP var_export false).
    $label = is_bool($flag) ? ($flag ? 'True' : 'False') : var_export($flag, true);
    it("happy: idempotent flag {$label} stored on definition [D-005]", function () use ($flag, $label) {
        $registry = DiscoveryHelpers::registry();
        $def = DiscoveryHelpers::mutatingWith($registry, 'idem-'.md5((string) $label), [
            'idempotent' => $flag,
        ]);
        $expected = CapabilityDefinition::normalizeIdempotent($flag);
        expect($def->idempotent)->toBe($expected);
    });
}

it('happy: readOnly=True stored on definition [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = Capability::define('ro-true')->readOnly(true)->surfaces(['http'])->register($registry);
    expect($def->readOnly)->toBeTrue();
});

it('happy: readOnly=False stored on definition [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'ro-false', ['readOnly' => false]);
    expect($def->readOnly)->toBeFalse();
});
