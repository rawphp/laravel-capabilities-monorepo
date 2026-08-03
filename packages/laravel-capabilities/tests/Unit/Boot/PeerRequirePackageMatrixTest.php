<?php

// REQ-014: require_package matrix (D-011). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\PeerSurfaceStatus;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

it('happy: boot ok when agent require=True installed=True mode=fail [D-011]', function () {
    $c = BootHelpers::peerCell('agent', true, true, 'fail', true);
    expect($c['threw'])->toBeNull()->and($c['registers'])->toBeTrue();
});

it('happy: boot ok when agent require=True installed=True mode=disable [D-011]', function () {
    $c = BootHelpers::peerCell('agent', true, true, 'disable', true);
    expect($c['threw'])->toBeNull()->and($c['registers'])->toBeTrue();
});

it('fail: boot fails when agent require=True installed=False mode=fail [D-011]', function () {
    $c = BootHelpers::peerCell('agent', false, false, 'fail', true);
    expect($c['threw'])->not->toBeNull()->and($c['registers'])->toBeFalse();
});

it('edge: soft disable when agent require=True installed=False mode=disable [D-011]', function () {
    $c = BootHelpers::peerCell('agent', false, false, 'disable', true);
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['status']->status)->toBe(PeerSurfaceStatus::DISABLED_INCOMPATIBLE);
});

it('happy: boot ok when agent require=False installed=True mode=fail [D-011]', function () {
    $c = BootHelpers::peerCell('agent', true, true, 'fail', false);
    expect($c['threw'])->toBeNull()->and($c['registers'])->toBeTrue();
});

it('happy: boot ok when agent require=False installed=True mode=disable [D-011]', function () {
    $c = BootHelpers::peerCell('agent', true, true, 'disable', false);
    expect($c['threw'])->toBeNull()->and($c['registers'])->toBeTrue();
});

it('edge: optional peer missing when agent require=False installed=False mode=fail [D-011]', function () {
    $c = BootHelpers::peerCell('agent', false, false, 'fail', false);
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['status']->reason)->toBe('missing_optional');
});

it('edge: optional peer missing when agent require=False installed=False mode=disable [D-011]', function () {
    $c = BootHelpers::peerCell('agent', false, false, 'disable', false);
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['status']->reason)->toBe('missing_optional');
});

it('happy: boot ok when mcp require=True installed=True mode=fail [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', true, true, 'fail', true);
    expect($c['threw'])->toBeNull()->and($c['registers'])->toBeTrue();
});

it('happy: boot ok when mcp require=True installed=True mode=disable [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', true, true, 'disable', true);
    expect($c['threw'])->toBeNull()->and($c['registers'])->toBeTrue();
});

it('fail: boot fails when mcp require=True installed=False mode=fail [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', false, false, 'fail', true);
    expect($c['threw'])->not->toBeNull()->and($c['registers'])->toBeFalse();
});

it('edge: soft disable when mcp require=True installed=False mode=disable [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', false, false, 'disable', true);
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['status']->status)->toBe(PeerSurfaceStatus::DISABLED_INCOMPATIBLE);
});

it('happy: boot ok when mcp require=False installed=True mode=fail [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', true, true, 'fail', false);
    expect($c['threw'])->toBeNull()->and($c['registers'])->toBeTrue();
});

it('happy: boot ok when mcp require=False installed=True mode=disable [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', true, true, 'disable', false);
    expect($c['threw'])->toBeNull()->and($c['registers'])->toBeTrue();
});

it('edge: optional peer missing when mcp require=False installed=False mode=fail [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', false, false, 'fail', false);
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['status']->reason)->toBe('missing_optional');
});

it('edge: optional peer missing when mcp require=False installed=False mode=disable [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', false, false, 'disable', false);
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['status']->reason)->toBe('missing_optional');
});
