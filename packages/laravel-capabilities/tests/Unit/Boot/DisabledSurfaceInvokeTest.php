<?php

// REQ-014: Invoke via disabled surface (SURF-003). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Boot\SurfaceRegistrar;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

it("fail: invoke via disabled surface agent is not registered [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["agent" => false]);
    expect(SurfaceRegistrar::isRegistered("agent", $surfaces))->toBeFalse();
});

it("fail: invoke via disabled surface agent does not reach domain [SURF-003]", function () {
    $h = BootHelpers::invokeHarness(["agent" => false]);
    $before = $h["runs"]->value;
    $result = BootHelpers::tryInvoke($h["registry"], $h["name"], "agent");
    expect($result->isOk())->toBeFalse()
        ->and($result->errorCode())->toBe("forbidden")
        ->and($h["runs"]->value)->toBe($before);
});

it("fail: invoke via disabled surface mcp is not registered [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["mcp" => false]);
    expect(SurfaceRegistrar::isRegistered("mcp", $surfaces))->toBeFalse();
});

it("fail: invoke via disabled surface mcp does not reach domain [SURF-003]", function () {
    $h = BootHelpers::invokeHarness(["mcp" => false]);
    $before = $h["runs"]->value;
    $result = BootHelpers::tryInvoke($h["registry"], $h["name"], "mcp");
    expect($result->isOk())->toBeFalse()
        ->and($result->errorCode())->toBe("forbidden")
        ->and($h["runs"]->value)->toBe($before);
});

it("fail: invoke via disabled surface http is not registered [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["http" => false]);
    expect(SurfaceRegistrar::isRegistered("http", $surfaces))->toBeFalse();
});

it("fail: invoke via disabled surface http does not reach domain [SURF-003]", function () {
    $h = BootHelpers::invokeHarness(["http" => false]);
    $before = $h["runs"]->value;
    $result = BootHelpers::tryInvoke($h["registry"], $h["name"], "http");
    expect($result->isOk())->toBeFalse()
        ->and($result->errorCode())->toBe("forbidden")
        ->and($h["runs"]->value)->toBe($before);
});

it("fail: invoke via disabled surface cli is not registered [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["cli" => false]);
    expect(SurfaceRegistrar::isRegistered("cli", $surfaces))->toBeFalse();
});

it("fail: invoke via disabled surface cli does not reach domain [SURF-003]", function () {
    $h = BootHelpers::invokeHarness(["cli" => false]);
    $before = $h["runs"]->value;
    $result = BootHelpers::tryInvoke($h["registry"], $h["name"], "cli");
    expect($result->isOk())->toBeFalse()
        ->and($result->errorCode())->toBe("forbidden")
        ->and($h["runs"]->value)->toBe($before);
});

it("fail: invoke via disabled surface job is not registered [SURF-003]", function () {
    $surfaces = BootHelpers::surfaces(["job" => false]);
    expect(SurfaceRegistrar::isRegistered("job", $surfaces))->toBeFalse();
});

it("fail: invoke via disabled surface job does not reach domain [SURF-003]", function () {
    $h = BootHelpers::invokeHarness(["job" => false]);
    $before = $h["runs"]->value;
    $result = BootHelpers::tryInvoke($h["registry"], $h["name"], "job");
    expect($result->isOk())->toBeFalse()
        ->and($result->errorCode())->toBe("forbidden")
        ->and($h["runs"]->value)->toBe($before);
});

