<?php

// REQ-014: Boot rules checklist (BOOT-RULE). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\PeerIncompatibleException;
use Rawphp\Capabilities\Boot\BootException;
use Rawphp\Capabilities\Boot\BootGuard;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\SurfaceNames;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

it('happy: boot rule: invoke surfaces default on [BOOT-RULE]', function () {
    $map = CapabilitiesConfig::globallyEnabledSurfaces();
    foreach (SurfaceNames::INVOKE_DEFAULT_ON as $s) {
        expect($map[$s])->toBeTrue();
    }
});

it('happy: boot rule: messaging defaults off in core [BOOT-RULE]', function () {
    expect(CapabilitiesConfig::globallyEnabledSurfaces()['messaging'])->toBeFalse();
});

it('happy: boot rule: missing peer with require_package fails or disables [BOOT-RULE]', function () {
    expect(BootHelpers::peerCell('agent', false, false, 'fail')['threw'])->toBeInstanceOf(PeerIncompatibleException::class);
    expect(BootHelpers::peerCell('agent', false, false, 'disable')['registers'])->toBeFalse();
});

it('happy: boot rule: incompatible peer same as missing [BOOT-RULE]', function () {
    expect(BootHelpers::peerCell('agent', true, false, 'fail')['threw'])->not->toBeNull();
    expect(BootHelpers::peerCell('agent', true, false, 'disable')['registers'])->toBeFalse();
});

it('happy: boot rule: cli requires http [BOOT-RULE]', function () {
    $config = BootHelpers::config(['surfaces' => BootHelpers::surfaces(['cli' => true, 'http' => false])]);
    expect(fn () => (new BootGuard(config: $config, probe: BootHelpers::probe()))->validate())
        ->toThrow(BootException::class);
});

it('happy: boot rule: messaging requires agent and package [BOOT-RULE]', function () {
    $config = BootHelpers::config(['surfaces' => BootHelpers::surfaces(['messaging' => true, 'agent' => false])]);
    expect(fn () => (new BootGuard(config: $config, probe: BootHelpers::probe(), messagingPackageInstalled: true))->validate())
        ->toThrow(BootException::class);
    $config2 = BootHelpers::config(['surfaces' => BootHelpers::surfaces(['messaging' => true, 'agent' => true])]);
    expect(fn () => (new BootGuard(config: $config2, probe: BootHelpers::probe(), messagingPackageInstalled: false))->validate())
        ->toThrow(BootException::class);
});

it('happy: boot rule: telegram secrets deferred to first traffic [BOOT-RULE]', function () {
    $g = new BootGuard(config: BootHelpers::config(), probe: BootHelpers::probe());
    expect($g->requiresMessagingSecretsAtBoot())->toBeFalse();
});

it('happy: boot rule: catalog only lists effective surfaces [BOOT-RULE]', function () {
    $def = new CapabilityDefinition(name: 'x', surfaces: ['agent', 'http'], readOnly: true);
    $global = BootHelpers::globalMap(['http' => false, 'agent' => true]);
    expect($def->effectiveSurfaces($global))->toBe(['agent']);
});

it('happy: boot rule: CI runs adapter contract tests before release [BOOT-RULE]', function () {
    expect(BootGuard::adapterContractTestsRequiredBeforeRelease())->toBeTrue();
    expect(file_exists(dirname(__DIR__, 2).'/tests/Unit/Adapters/ContractTableTest.php')
        || file_exists(dirname(__DIR__, 3).'/packages/laravel-capabilities/tests/Unit/Adapters/ContractTableTest.php')
        || file_exists(__DIR__.'/../Adapters/ContractTableTest.php'))->toBeTrue();
});

it('happy: boot rule: SKIP_BOOT_CHECKS forbidden in production [BOOT-RULE]', function () {
    $g = new BootGuard(appEnv: 'production', skipBootChecks: true, probe: BootHelpers::probe(), config: BootHelpers::config());
    expect($g->shouldSkipDeferredChecks())->toBeFalse();
});
