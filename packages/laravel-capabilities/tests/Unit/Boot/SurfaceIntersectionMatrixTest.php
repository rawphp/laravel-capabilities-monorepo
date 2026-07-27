<?php

// REQ-014: Effective exposure matrix (SURF-001). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Boot\BootGuard;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

it("happy: effective exposure true when surface=agent global=True capability_lists=True [SURF-001]", function () {
    $global = BootHelpers::globalMap(["agent" => true]);
    $cap = ["agent"];
    expect(BootGuard::isEffectivelyExposed("agent", $cap, $global))->toBe(true);
});

it("edge: effective exposure false when surface=agent global=True capability_lists=False [SURF-001]", function () {
    $global = BootHelpers::globalMap(["agent" => true]);
    $cap = ["http"];
    expect(BootGuard::isEffectivelyExposed("agent", $cap, $global))->toBe(false);
});

it("edge: effective exposure false when surface=agent global=False capability_lists=True [SURF-001]", function () {
    $global = BootHelpers::globalMap(["agent" => false]);
    $cap = ["agent"];
    expect(BootGuard::isEffectivelyExposed("agent", $cap, $global))->toBe(false);
});

it("edge: effective exposure false when surface=agent global=False capability_lists=False [SURF-001]", function () {
    $global = BootHelpers::globalMap(["agent" => false]);
    $cap = ["http"];
    expect(BootGuard::isEffectivelyExposed("agent", $cap, $global))->toBe(false);
});

it("happy: effective exposure true when surface=mcp global=True capability_lists=True [SURF-001]", function () {
    $global = BootHelpers::globalMap(["mcp" => true]);
    $cap = ["mcp"];
    expect(BootGuard::isEffectivelyExposed("mcp", $cap, $global))->toBe(true);
});

it("edge: effective exposure false when surface=mcp global=True capability_lists=False [SURF-001]", function () {
    $global = BootHelpers::globalMap(["mcp" => true]);
    $cap = ["http"];
    expect(BootGuard::isEffectivelyExposed("mcp", $cap, $global))->toBe(false);
});

it("edge: effective exposure false when surface=mcp global=False capability_lists=True [SURF-001]", function () {
    $global = BootHelpers::globalMap(["mcp" => false]);
    $cap = ["mcp"];
    expect(BootGuard::isEffectivelyExposed("mcp", $cap, $global))->toBe(false);
});

it("edge: effective exposure false when surface=mcp global=False capability_lists=False [SURF-001]", function () {
    $global = BootHelpers::globalMap(["mcp" => false]);
    $cap = ["http"];
    expect(BootGuard::isEffectivelyExposed("mcp", $cap, $global))->toBe(false);
});

it("happy: effective exposure true when surface=http global=True capability_lists=True [SURF-001]", function () {
    $global = BootHelpers::globalMap(["http" => true]);
    $cap = ["http"];
    expect(BootGuard::isEffectivelyExposed("http", $cap, $global))->toBe(true);
});

it("edge: effective exposure false when surface=http global=True capability_lists=False [SURF-001]", function () {
    $global = BootHelpers::globalMap(["http" => true]);
    $cap = ["agent"];
    expect(BootGuard::isEffectivelyExposed("http", $cap, $global))->toBe(false);
});

it("edge: effective exposure false when surface=http global=False capability_lists=True [SURF-001]", function () {
    $global = BootHelpers::globalMap(["http" => false]);
    $cap = ["http"];
    expect(BootGuard::isEffectivelyExposed("http", $cap, $global))->toBe(false);
});

it("edge: effective exposure false when surface=http global=False capability_lists=False [SURF-001]", function () {
    $global = BootHelpers::globalMap(["http" => false]);
    $cap = ["agent"];
    expect(BootGuard::isEffectivelyExposed("http", $cap, $global))->toBe(false);
});

it("happy: effective exposure true when surface=cli global=True capability_lists=True [SURF-001]", function () {
    $global = BootHelpers::globalMap(["cli" => true]);
    $cap = ["cli"];
    expect(BootGuard::isEffectivelyExposed("cli", $cap, $global))->toBe(true);
});

it("edge: effective exposure false when surface=cli global=True capability_lists=False [SURF-001]", function () {
    $global = BootHelpers::globalMap(["cli" => true]);
    $cap = ["http"];
    expect(BootGuard::isEffectivelyExposed("cli", $cap, $global))->toBe(false);
});

it("edge: effective exposure false when surface=cli global=False capability_lists=True [SURF-001]", function () {
    $global = BootHelpers::globalMap(["cli" => false]);
    $cap = ["cli"];
    expect(BootGuard::isEffectivelyExposed("cli", $cap, $global))->toBe(false);
});

it("edge: effective exposure false when surface=cli global=False capability_lists=False [SURF-001]", function () {
    $global = BootHelpers::globalMap(["cli" => false]);
    $cap = ["http"];
    expect(BootGuard::isEffectivelyExposed("cli", $cap, $global))->toBe(false);
});

it("happy: effective exposure true when surface=job global=True capability_lists=True [SURF-001]", function () {
    $global = BootHelpers::globalMap(["job" => true]);
    $cap = ["job"];
    expect(BootGuard::isEffectivelyExposed("job", $cap, $global))->toBe(true);
});

it("edge: effective exposure false when surface=job global=True capability_lists=False [SURF-001]", function () {
    $global = BootHelpers::globalMap(["job" => true]);
    $cap = ["http"];
    expect(BootGuard::isEffectivelyExposed("job", $cap, $global))->toBe(false);
});

it("edge: effective exposure false when surface=job global=False capability_lists=True [SURF-001]", function () {
    $global = BootHelpers::globalMap(["job" => false]);
    $cap = ["job"];
    expect(BootGuard::isEffectivelyExposed("job", $cap, $global))->toBe(false);
});

it("edge: effective exposure false when surface=job global=False capability_lists=False [SURF-001]", function () {
    $global = BootHelpers::globalMap(["job" => false]);
    $cap = ["http"];
    expect(BootGuard::isEffectivelyExposed("job", $cap, $global))->toBe(false);
});

it("happy: effective exposure true when surface=artisan global=True capability_lists=True [SURF-001]", function () {
    $global = BootHelpers::globalMap(["artisan" => true]);
    $cap = ["artisan"];
    expect(BootGuard::isEffectivelyExposed("artisan", $cap, $global))->toBe(true);
});

it("edge: effective exposure false when surface=artisan global=True capability_lists=False [SURF-001]", function () {
    $global = BootHelpers::globalMap(["artisan" => true]);
    $cap = ["http"];
    expect(BootGuard::isEffectivelyExposed("artisan", $cap, $global))->toBe(false);
});

it("edge: effective exposure false when surface=artisan global=False capability_lists=True [SURF-001]", function () {
    $global = BootHelpers::globalMap(["artisan" => false]);
    $cap = ["artisan"];
    expect(BootGuard::isEffectivelyExposed("artisan", $cap, $global))->toBe(false);
});

it("edge: effective exposure false when surface=artisan global=False capability_lists=False [SURF-001]", function () {
    $global = BootHelpers::globalMap(["artisan" => false]);
    $cap = ["http"];
    expect(BootGuard::isEffectivelyExposed("artisan", $cap, $global))->toBe(false);
});

it("fail: capability listing agent cannot enable when global agent disabled [SURF-001]", function () {
    $global = BootHelpers::globalMap(["agent" => false]);
    expect(BootGuard::isEffectivelyExposed("agent", ["agent"], $global))->toBeFalse();
});

it("fail: capability listing mcp cannot enable when global mcp disabled [SURF-001]", function () {
    $global = BootHelpers::globalMap(["mcp" => false]);
    expect(BootGuard::isEffectivelyExposed("mcp", ["mcp"], $global))->toBeFalse();
});

it("fail: capability listing http cannot enable when global http disabled [SURF-001]", function () {
    $global = BootHelpers::globalMap(["http" => false]);
    expect(BootGuard::isEffectivelyExposed("http", ["http"], $global))->toBeFalse();
});

it("fail: capability listing cli cannot enable when global cli disabled [SURF-001]", function () {
    $global = BootHelpers::globalMap(["cli" => false]);
    expect(BootGuard::isEffectivelyExposed("cli", ["cli"], $global))->toBeFalse();
});

it("fail: capability listing job cannot enable when global job disabled [SURF-001]", function () {
    $global = BootHelpers::globalMap(["job" => false]);
    expect(BootGuard::isEffectivelyExposed("job", ["job"], $global))->toBeFalse();
});

it("fail: capability listing artisan cannot enable when global artisan disabled [SURF-001]", function () {
    $global = BootHelpers::globalMap(["artisan" => false]);
    expect(BootGuard::isEffectivelyExposed("artisan", ["artisan"], $global))->toBeFalse();
});

