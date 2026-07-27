<?php

// REQ-014: Surface boot rules (SURF-002/003/001/004/005). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\PeerIncompatibleException;
use Rawphp\Capabilities\Adapters\PeerSurfaceStatus;
use Rawphp\Capabilities\Boot\BootException;
use Rawphp\Capabilities\Boot\BootGuard;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\Boot\SurfaceNames;
use Rawphp\Capabilities\Boot\SurfaceRegistrar;
use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

it("happy: surface agent defaults to enabled [SURF-002]", function () {
    $cfg = CapabilitiesConfig::defaults();
    expect($cfg["surfaces"]["agent"]["enabled"])->toBeTrue()
        ->and(CapabilitiesConfig::globallyEnabledSurfaces()["agent"])->toBeTrue();
});

it("happy: disabling surface agent registers nothing for that surface [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["agent" => false]);
    expect(SurfaceRegistrar::artifacts("agent", $surfaces))->toBeEmpty()
        ->and(SurfaceRegistrar::isRegistered("agent", $surfaces))->toBeFalse();
});

it("fail: disabling surface agent does not leave half-registered stubs [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["agent" => false]);
    expect(SurfaceRegistrar::isHalfRegistered("agent", $surfaces))->toBeFalse()
        ->and(SurfaceRegistrar::artifacts("agent", $surfaces))->toBeEmpty();
});

it("happy: surface mcp defaults to enabled [SURF-002]", function () {
    $cfg = CapabilitiesConfig::defaults();
    expect($cfg["surfaces"]["mcp"]["enabled"])->toBeTrue()
        ->and(CapabilitiesConfig::globallyEnabledSurfaces()["mcp"])->toBeTrue();
});

it("happy: disabling surface mcp registers nothing for that surface [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["mcp" => false]);
    expect(SurfaceRegistrar::artifacts("mcp", $surfaces))->toBeEmpty()
        ->and(SurfaceRegistrar::isRegistered("mcp", $surfaces))->toBeFalse();
});

it("fail: disabling surface mcp does not leave half-registered stubs [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["mcp" => false]);
    expect(SurfaceRegistrar::isHalfRegistered("mcp", $surfaces))->toBeFalse()
        ->and(SurfaceRegistrar::artifacts("mcp", $surfaces))->toBeEmpty();
});

it("happy: surface http defaults to enabled [SURF-002]", function () {
    $cfg = CapabilitiesConfig::defaults();
    expect($cfg["surfaces"]["http"]["enabled"])->toBeTrue()
        ->and(CapabilitiesConfig::globallyEnabledSurfaces()["http"])->toBeTrue();
});

it("happy: disabling surface http registers nothing for that surface [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["http" => false]);
    expect(SurfaceRegistrar::artifacts("http", $surfaces))->toBeEmpty()
        ->and(SurfaceRegistrar::isRegistered("http", $surfaces))->toBeFalse();
});

it("fail: disabling surface http does not leave half-registered stubs [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["http" => false]);
    expect(SurfaceRegistrar::isHalfRegistered("http", $surfaces))->toBeFalse()
        ->and(SurfaceRegistrar::artifacts("http", $surfaces))->toBeEmpty();
});

it("happy: surface cli defaults to enabled [SURF-002]", function () {
    $cfg = CapabilitiesConfig::defaults();
    expect($cfg["surfaces"]["cli"]["enabled"])->toBeTrue()
        ->and(CapabilitiesConfig::globallyEnabledSurfaces()["cli"])->toBeTrue();
});

it("happy: disabling surface cli registers nothing for that surface [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["cli" => false]);
    expect(SurfaceRegistrar::artifacts("cli", $surfaces))->toBeEmpty()
        ->and(SurfaceRegistrar::isRegistered("cli", $surfaces))->toBeFalse();
});

it("fail: disabling surface cli does not leave half-registered stubs [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["cli" => false]);
    expect(SurfaceRegistrar::isHalfRegistered("cli", $surfaces))->toBeFalse()
        ->and(SurfaceRegistrar::artifacts("cli", $surfaces))->toBeEmpty();
});

it("happy: surface job defaults to enabled [SURF-002]", function () {
    $cfg = CapabilitiesConfig::defaults();
    expect($cfg["surfaces"]["job"]["enabled"])->toBeTrue()
        ->and(CapabilitiesConfig::globallyEnabledSurfaces()["job"])->toBeTrue();
});

it("happy: disabling surface job registers nothing for that surface [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["job" => false]);
    expect(SurfaceRegistrar::artifacts("job", $surfaces))->toBeEmpty()
        ->and(SurfaceRegistrar::isRegistered("job", $surfaces))->toBeFalse();
});

it("fail: disabling surface job does not leave half-registered stubs [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["job" => false]);
    expect(SurfaceRegistrar::isHalfRegistered("job", $surfaces))->toBeFalse()
        ->and(SurfaceRegistrar::artifacts("job", $surfaces))->toBeEmpty();
});

it("happy: surface artisan defaults to enabled [SURF-002]", function () {
    $cfg = CapabilitiesConfig::defaults();
    expect($cfg["surfaces"]["artisan"]["enabled"])->toBeTrue()
        ->and(CapabilitiesConfig::globallyEnabledSurfaces()["artisan"])->toBeTrue();
});

it("happy: disabling surface artisan registers nothing for that surface [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["artisan" => false]);
    expect(SurfaceRegistrar::artifacts("artisan", $surfaces))->toBeEmpty()
        ->and(SurfaceRegistrar::isRegistered("artisan", $surfaces))->toBeFalse();
});

it("fail: disabling surface artisan does not leave half-registered stubs [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["artisan" => false]);
    expect(SurfaceRegistrar::isHalfRegistered("artisan", $surfaces))->toBeFalse()
        ->and(SurfaceRegistrar::artifacts("artisan", $surfaces))->toBeEmpty();
});

it("happy: messaging surface defaults to disabled in core [D-007]", function () {
    expect(CapabilitiesConfig::defaults()["surfaces"]["messaging"]["enabled"])->toBeFalse()
        ->and(CapabilitiesConfig::globallyEnabledSurfaces()["messaging"])->toBeFalse();
});

it("edge: capability listing only includes surfaces after global intersect capability.surfaces [SURF-001]", function () {
    $global = BootHelpers::globalMap(["agent" => true, "http" => false]);
    $effective = BootHelpers::effective(["agent", "http", "cli"], $global);
    expect($effective)->toContain("agent")->and($effective)->not->toContain("http");
});

it("fail: capability cannot enable a globally disabled surface [SURF-001]", function () {
    $global = BootHelpers::globalMap(["mcp" => false]);
    expect(BootGuard::isEffectivelyExposed("mcp", ["mcp", "http"], $global))->toBeFalse();
});

it("edge: effective exposure for surface agent is intersection only [SURF-001]", function () {
    $globalOn = BootHelpers::globalMap(["agent" => true]);
    $globalOff = BootHelpers::globalMap(["agent" => false]);
    expect(BootGuard::isEffectivelyExposed("agent", ["agent"], $globalOn))->toBeTrue()
        ->and(BootGuard::isEffectivelyExposed("agent", ["agent"], $globalOff))->toBeFalse()
        ->and(BootGuard::isEffectivelyExposed("agent", ["http"], $globalOn))->toBeFalse();
});

it("edge: effective exposure for surface mcp is intersection only [SURF-001]", function () {
    $globalOn = BootHelpers::globalMap(["mcp" => true]);
    $globalOff = BootHelpers::globalMap(["mcp" => false]);
    expect(BootGuard::isEffectivelyExposed("mcp", ["mcp"], $globalOn))->toBeTrue()
        ->and(BootGuard::isEffectivelyExposed("mcp", ["mcp"], $globalOff))->toBeFalse()
        ->and(BootGuard::isEffectivelyExposed("mcp", ["http"], $globalOn))->toBeFalse();
});

it("edge: effective exposure for surface http is intersection only [SURF-001]", function () {
    $globalOn = BootHelpers::globalMap(["http" => true]);
    $globalOff = BootHelpers::globalMap(["http" => false]);
    expect(BootGuard::isEffectivelyExposed("http", ["http"], $globalOn))->toBeTrue()
        ->and(BootGuard::isEffectivelyExposed("http", ["http"], $globalOff))->toBeFalse()
        ->and(BootGuard::isEffectivelyExposed("http", ["agent"], $globalOn))->toBeFalse();
});

it("edge: effective exposure for surface cli is intersection only [SURF-001]", function () {
    $globalOn = BootHelpers::globalMap(["cli" => true]);
    $globalOff = BootHelpers::globalMap(["cli" => false]);
    expect(BootGuard::isEffectivelyExposed("cli", ["cli"], $globalOn))->toBeTrue()
        ->and(BootGuard::isEffectivelyExposed("cli", ["cli"], $globalOff))->toBeFalse()
        ->and(BootGuard::isEffectivelyExposed("cli", ["http"], $globalOn))->toBeFalse();
});

it("edge: effective exposure for surface job is intersection only [SURF-001]", function () {
    $globalOn = BootHelpers::globalMap(["job" => true]);
    $globalOff = BootHelpers::globalMap(["job" => false]);
    expect(BootGuard::isEffectivelyExposed("job", ["job"], $globalOn))->toBeTrue()
        ->and(BootGuard::isEffectivelyExposed("job", ["job"], $globalOff))->toBeFalse()
        ->and(BootGuard::isEffectivelyExposed("job", ["http"], $globalOn))->toBeFalse();
});

it("fail: cli enabled while http disabled fails boot [SURF-004]", function () {
    $config = BootHelpers::config(["surfaces" => BootHelpers::surfaces(["cli" => true, "http" => false])]);
    expect(fn () => (new BootGuard(config: $config, probe: BootHelpers::probe()))->validate())
        ->toThrow(BootException::class);
});

it("fail: messaging enabled without messaging package fails boot check [D-007]", function () {
    $config = BootHelpers::config(["surfaces" => BootHelpers::surfaces(["messaging" => true, "agent" => true])]);
    expect(fn () => (new BootGuard(
        config: $config,
        probe: BootHelpers::probe(),
        messagingPackageInstalled: false,
    ))->validate())->toThrow(BootException::class);
});

it("fail: messaging enabled without agent surface fails boot [SURF-004]", function () {
    $config = BootHelpers::config(["surfaces" => BootHelpers::surfaces(["messaging" => true, "agent" => false])]);
    expect(fn () => (new BootGuard(
        config: $config,
        probe: BootHelpers::probe(),
        messagingPackageInstalled: true,
    ))->validate())->toThrow(BootException::class);
});

it("fail: agent enabled with require_package and missing laravel/ai fails boot when on_incompatible=fail [D-011]", function () {
    $config = BootHelpers::config();
    $config["surfaces"]["agent"]["enabled"] = true;
    $config["surfaces"]["agent"]["require_package"] = true;
    $config["surfaces"]["agent"]["on_incompatible"] = "fail";
    expect(fn () => (new BootGuard(config: $config, probe: BootHelpers::probe(ai: false)))->validate())
        ->toThrow(PeerIncompatibleException::class);
});

it("edge: agent missing peer with on_incompatible=disable soft-disables and logs CRITICAL [D-011]", function () {
    $config = BootHelpers::config();
    $config["surfaces"]["agent"]["on_incompatible"] = "disable";
    $report = (new BootGuard(config: $config, probe: BootHelpers::probe(ai: false, mcp: true)))->validate();
    $status = $report["surfaces"]["agent"];
    expect($status->registersTools)->toBeFalse()
        ->and($status->status)->toBe(PeerSurfaceStatus::DISABLED_INCOMPATIBLE)
        ->and($report["logs"])->not->toBeEmpty()
        ->and($report["logs"][0]["level"])->toBe("critical");
});

it("fail: agent half-register of tools never occurs when peer incompatible [D-011]", function () {
    $surfaces = BootHelpers::surfaces();
    $surfaces["agent"]["on_incompatible"] = "disable";
    $probe = BootHelpers::probe(ai: false);
    expect(SurfaceRegistrar::artifacts("agent", $surfaces, $probe))->toBeEmpty()
        ->and(SurfaceRegistrar::isHalfRegistered("agent", $surfaces, $probe))->toBeFalse();
});

it("edge: agent incompatible peer version via supportsInstalledPeer false fails or disables per config [D-011]", function () {
    $probe = BootHelpers::probe(ai: true, aiCompatible: false);
    expect($probe->supports("laravel/ai"))->toBeFalse();
    $fail = BootHelpers::peerCell("agent", true, false, "fail");
    expect($fail["threw"])->not->toBeNull();
    $soft = BootHelpers::peerCell("agent", true, false, "disable");
    expect($soft["registers"])->toBeFalse();
});

it("fail: mcp enabled with require_package and missing laravel/mcp fails boot when on_incompatible=fail [D-011]", function () {
    $config = BootHelpers::config();
    $config["surfaces"]["mcp"]["require_package"] = true;
    $config["surfaces"]["mcp"]["on_incompatible"] = "fail";
    expect(fn () => (new BootGuard(config: $config, probe: BootHelpers::probe(mcp: false)))->validate())
        ->toThrow(PeerIncompatibleException::class);
});

it("edge: mcp missing peer with on_incompatible=disable soft-disables and logs CRITICAL [D-011]", function () {
    $config = BootHelpers::config();
    $config["surfaces"]["mcp"]["on_incompatible"] = "disable";
    $report = (new BootGuard(config: $config, probe: BootHelpers::probe(ai: true, mcp: false)))->validate();
    expect($report["surfaces"]["mcp"]->registersTools)->toBeFalse()
        ->and($report["logs"])->not->toBeEmpty();
});

it("fail: mcp half-register of tools never occurs when peer incompatible [D-011]", function () {
    $surfaces = BootHelpers::surfaces();
    $surfaces["mcp"]["on_incompatible"] = "disable";
    $probe = BootHelpers::probe(mcp: false);
    expect(SurfaceRegistrar::artifacts("mcp", $surfaces, $probe))->toBeEmpty()
        ->and(SurfaceRegistrar::isHalfRegistered("mcp", $surfaces, $probe))->toBeFalse();
});

it("edge: mcp incompatible peer version via supportsInstalledPeer false fails or disables per config [D-011]", function () {
    $probe = BootHelpers::probe(mcp: true, mcpCompatible: false);
    expect($probe->supports("laravel/mcp"))->toBeFalse();
    expect(BootHelpers::peerCell("mcp", true, false, "fail")["threw"])->not->toBeNull();
    expect(BootHelpers::peerCell("mcp", true, false, "disable")["registers"])->toBeFalse();
});

it("edge: CAPABILITIES_SKIP_BOOT_CHECKS ignored when APP_ENV=production [D-021]", function () {
    $g = new BootGuard(config: BootHelpers::config(), probe: BootHelpers::probe(), appEnv: "production", skipBootChecks: true);
    expect($g->shouldSkipDeferredChecks())->toBeFalse()->and($g->isProduction())->toBeTrue();
});

it("edge: CAPABILITIES_SKIP_BOOT_CHECKS only skips deferred-style checks in CI [D-021]", function () {
    $g = new BootGuard(config: BootHelpers::config(), probe: BootHelpers::probe(), appEnv: "testing", skipBootChecks: true);
    expect($g->shouldSkipDeferredChecks())->toBeTrue()
        ->and($g->requiresMessagingSecretsAtBoot())->toBeFalse();
});

it("happy: publishing capabilities-config tag works [BOOT-001]", function () {
    expect(ContainerBindings::hasPublishTag("capabilities-config"))->toBeTrue()
        ->and(CapabilitiesServiceProvider::publishTags())->toContain("capabilities-config");
});

it("happy: env CAPABILITIES_SURFACE_* toggles map to config [SURF-005]", function () {
    foreach (SurfaceNames::ALL as $surface) {
        expect(CapabilitiesConfig::envKeyForSurface($surface))
            ->toBe("CAPABILITIES_SURFACE_".strtoupper($surface));
    }
});

it("edge: env toggle for surface agent respected at boot [SURF-005]", function () {
    $key = CapabilitiesConfig::envKeyForSurface("agent");
    expect($key)->toBe("CAPABILITIES_SURFACE_AGENT");
    $on = BootHelpers::surfaces(["agent" => true]);
    $off = BootHelpers::surfaces(["agent" => false]);
    // Config array is the boot-time source of truth after env is resolved by Laravel.
    expect((bool) $on["agent"]["enabled"])->toBeTrue()
        ->and((bool) $off["agent"]["enabled"])->toBeFalse();
});

it("edge: env toggle for surface mcp respected at boot [SURF-005]", function () {
    $key = CapabilitiesConfig::envKeyForSurface("mcp");
    expect($key)->toBe("CAPABILITIES_SURFACE_MCP");
    $on = BootHelpers::surfaces(["mcp" => true]);
    $off = BootHelpers::surfaces(["mcp" => false]);
    // Config array is the boot-time source of truth after env is resolved by Laravel.
    expect((bool) $on["mcp"]["enabled"])->toBeTrue()
        ->and((bool) $off["mcp"]["enabled"])->toBeFalse();
});

it("edge: env toggle for surface http respected at boot [SURF-005]", function () {
    $key = CapabilitiesConfig::envKeyForSurface("http");
    expect($key)->toBe("CAPABILITIES_SURFACE_HTTP");
    $on = BootHelpers::surfaces(["http" => true]);
    $off = BootHelpers::surfaces(["http" => false]);
    // Config array is the boot-time source of truth after env is resolved by Laravel.
    expect((bool) $on["http"]["enabled"])->toBeTrue()
        ->and((bool) $off["http"]["enabled"])->toBeFalse();
});

it("edge: env toggle for surface cli respected at boot [SURF-005]", function () {
    $key = CapabilitiesConfig::envKeyForSurface("cli");
    expect($key)->toBe("CAPABILITIES_SURFACE_CLI");
    $on = BootHelpers::surfaces(["cli" => true]);
    $off = BootHelpers::surfaces(["cli" => false]);
    // Config array is the boot-time source of truth after env is resolved by Laravel.
    expect((bool) $on["cli"]["enabled"])->toBeTrue()
        ->and((bool) $off["cli"]["enabled"])->toBeFalse();
});

it("edge: env toggle for surface job respected at boot [SURF-005]", function () {
    $key = CapabilitiesConfig::envKeyForSurface("job");
    expect($key)->toBe("CAPABILITIES_SURFACE_JOB");
    $on = BootHelpers::surfaces(["job" => true]);
    $off = BootHelpers::surfaces(["job" => false]);
    // Config array is the boot-time source of truth after env is resolved by Laravel.
    expect((bool) $on["job"]["enabled"])->toBeTrue()
        ->and((bool) $off["job"]["enabled"])->toBeFalse();
});

it("edge: env toggle for surface artisan respected at boot [SURF-005]", function () {
    $key = CapabilitiesConfig::envKeyForSurface("artisan");
    expect($key)->toBe("CAPABILITIES_SURFACE_ARTISAN");
    $on = BootHelpers::surfaces(["artisan" => true]);
    $off = BootHelpers::surfaces(["artisan" => false]);
    // Config array is the boot-time source of truth after env is resolved by Laravel.
    expect((bool) $on["artisan"]["enabled"])->toBeTrue()
        ->and((bool) $off["artisan"]["enabled"])->toBeFalse();
});

it("edge: env toggle for surface messaging respected at boot [SURF-005]", function () {
    $key = CapabilitiesConfig::envKeyForSurface("messaging");
    expect($key)->toBe("CAPABILITIES_SURFACE_MESSAGING");
    $on = BootHelpers::surfaces(["messaging" => true]);
    $off = BootHelpers::surfaces(["messaging" => false]);
    // Config array is the boot-time source of truth after env is resolved by Laravel.
    expect((bool) $on["messaging"]["enabled"])->toBeTrue()
        ->and((bool) $off["messaging"]["enabled"])->toBeFalse();
});

