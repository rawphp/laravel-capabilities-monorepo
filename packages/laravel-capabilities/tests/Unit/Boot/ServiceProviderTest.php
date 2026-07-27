<?php

// REQ-014: Service provider registration plan (BOOT-001 / SURF-003). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\SurfaceNames;
use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

it("happy: registers config merge [BOOT-001]", function () {
    $plan = CapabilitiesServiceProvider::registrationPlan();
    expect($plan["config_merged"])->toBeTrue()
        ->and(CapabilitiesConfig::defaults())->toHaveKeys(CapabilitiesConfig::TOP_LEVEL_KEYS);
});

it("happy: registers registry singleton [BOOT-001]", function () {
    $plan = CapabilitiesServiceProvider::registrationPlan();
    expect($plan["registry_singleton"])->toBeTrue()
        ->and($plan["bindings"])->toContain("CapabilityRegistry");
});

it("edge: registers routes when http enabled [BOOT-001]", function () {
    $plan = CapabilitiesServiceProvider::registrationPlan(BootHelpers::config([
        "surfaces" => BootHelpers::surfaces(["http" => true]),
    ]));
    expect($plan["routes"])->not->toBeEmpty()->and($plan["routes"])->toContain("invoke");
});

it("edge: registers commands when artisan enabled [BOOT-001]", function () {
    $plan = CapabilitiesServiceProvider::registrationPlan(BootHelpers::config([
        "surfaces" => BootHelpers::surfaces(["artisan" => true]),
    ]));
    expect($plan["commands"])->not->toBeEmpty();
});

it("fail: does not register AI tools when agent disabled [SURF-003]", function () {
    $plan = CapabilitiesServiceProvider::registrationPlan(BootHelpers::config([
        "surfaces" => BootHelpers::surfaces(["agent" => false]),
    ]), BootHelpers::probe());
    expect($plan["ai_tools"])->toBeEmpty()
        ->and($plan["surfaces"][SurfaceNames::AGENT])->toBeEmpty();
});

it("fail: does not register MCP tools when mcp disabled [SURF-003]", function () {
    $plan = CapabilitiesServiceProvider::registrationPlan(BootHelpers::config([
        "surfaces" => BootHelpers::surfaces(["mcp" => false]),
    ]), BootHelpers::probe());
    expect($plan["mcp_tools"])->toBeEmpty()
        ->and($plan["surfaces"][SurfaceNames::MCP])->toBeEmpty();
});
