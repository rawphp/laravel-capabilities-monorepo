<?php

// REQ-014: Peer install/compatibility matrix (D-011). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\PeerSurfaceStatus;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

it('happy: agent peer=laravel/ai installed=True compatible=True mode=fail registers [D-011]', function () {
    $c = BootHelpers::peerCell('agent', true, true, 'fail');
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeTrue()
        ->and($c['status']->status)->toBe(PeerSurfaceStatus::UP)
        ->and($c['artifacts'])->not->toBeEmpty();
});

it('happy: agent peer=laravel/ai installed=True compatible=True mode=disable registers [D-011]', function () {
    $c = BootHelpers::peerCell('agent', true, true, 'disable');
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeTrue()
        ->and($c['status']->status)->toBe(PeerSurfaceStatus::UP)
        ->and($c['artifacts'])->not->toBeEmpty();
});

it('fail: agent peer=laravel/ai installed=True compatible=False mode=fail boots failed [D-011]', function () {
    $c = BootHelpers::peerCell('agent', true, false, 'fail');
    expect($c['threw'])->not->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['artifacts'])->toBeEmpty();
});

it('fail: agent peer=laravel/ai installed=True compatible=False mode=fail never half-registers [D-011]', function () {
    $c = BootHelpers::peerCell('agent', true, false, 'fail');
    expect($c['registers'])->toBeFalse()->and($c['artifacts'])->toBeEmpty();
});

it('edge: agent peer=laravel/ai installed=True compatible=False mode=disable soft-disables [D-011]', function () {
    $c = BootHelpers::peerCell('agent', true, false, 'disable');
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['status']->status)->toBe(PeerSurfaceStatus::DISABLED_INCOMPATIBLE)
        ->and($c['status']->logs)->not->toBeEmpty();
});

it('fail: agent peer=laravel/ai installed=True compatible=False mode=disable never half-registers [D-011]', function () {
    $c = BootHelpers::peerCell('agent', true, false, 'disable');
    expect($c['registers'])->toBeFalse()->and($c['artifacts'])->toBeEmpty();
});

it('fail: agent peer=laravel/ai installed=False compatible=True mode=fail boots failed [D-011]', function () {
    $c = BootHelpers::peerCell('agent', false, true, 'fail');
    expect($c['threw'])->not->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['artifacts'])->toBeEmpty();
});

it('fail: agent peer=laravel/ai installed=False compatible=True mode=fail never half-registers [D-011]', function () {
    $c = BootHelpers::peerCell('agent', false, true, 'fail');
    expect($c['registers'])->toBeFalse()->and($c['artifacts'])->toBeEmpty();
});

it('edge: agent peer=laravel/ai installed=False compatible=True mode=disable soft-disables [D-011]', function () {
    $c = BootHelpers::peerCell('agent', false, true, 'disable');
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['status']->status)->toBe(PeerSurfaceStatus::DISABLED_INCOMPATIBLE)
        ->and($c['status']->logs)->not->toBeEmpty();
});

it('fail: agent peer=laravel/ai installed=False compatible=True mode=disable never half-registers [D-011]', function () {
    $c = BootHelpers::peerCell('agent', false, true, 'disable');
    expect($c['registers'])->toBeFalse()->and($c['artifacts'])->toBeEmpty();
});

it('fail: agent peer=laravel/ai installed=False compatible=False mode=fail boots failed [D-011]', function () {
    $c = BootHelpers::peerCell('agent', false, false, 'fail');
    expect($c['threw'])->not->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['artifacts'])->toBeEmpty();
});

it('fail: agent peer=laravel/ai installed=False compatible=False mode=fail never half-registers [D-011]', function () {
    $c = BootHelpers::peerCell('agent', false, false, 'fail');
    expect($c['registers'])->toBeFalse()->and($c['artifacts'])->toBeEmpty();
});

it('edge: agent peer=laravel/ai installed=False compatible=False mode=disable soft-disables [D-011]', function () {
    $c = BootHelpers::peerCell('agent', false, false, 'disable');
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['status']->status)->toBe(PeerSurfaceStatus::DISABLED_INCOMPATIBLE)
        ->and($c['status']->logs)->not->toBeEmpty();
});

it('fail: agent peer=laravel/ai installed=False compatible=False mode=disable never half-registers [D-011]', function () {
    $c = BootHelpers::peerCell('agent', false, false, 'disable');
    expect($c['registers'])->toBeFalse()->and($c['artifacts'])->toBeEmpty();
});

it('happy: mcp peer=laravel/mcp installed=True compatible=True mode=fail registers [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', true, true, 'fail');
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeTrue()
        ->and($c['status']->status)->toBe(PeerSurfaceStatus::UP)
        ->and($c['artifacts'])->not->toBeEmpty();
});

it('happy: mcp peer=laravel/mcp installed=True compatible=True mode=disable registers [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', true, true, 'disable');
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeTrue()
        ->and($c['status']->status)->toBe(PeerSurfaceStatus::UP)
        ->and($c['artifacts'])->not->toBeEmpty();
});

it('fail: mcp peer=laravel/mcp installed=True compatible=False mode=fail boots failed [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', true, false, 'fail');
    expect($c['threw'])->not->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['artifacts'])->toBeEmpty();
});

it('fail: mcp peer=laravel/mcp installed=True compatible=False mode=fail never half-registers [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', true, false, 'fail');
    expect($c['registers'])->toBeFalse()->and($c['artifacts'])->toBeEmpty();
});

it('edge: mcp peer=laravel/mcp installed=True compatible=False mode=disable soft-disables [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', true, false, 'disable');
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['status']->status)->toBe(PeerSurfaceStatus::DISABLED_INCOMPATIBLE)
        ->and($c['status']->logs)->not->toBeEmpty();
});

it('fail: mcp peer=laravel/mcp installed=True compatible=False mode=disable never half-registers [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', true, false, 'disable');
    expect($c['registers'])->toBeFalse()->and($c['artifacts'])->toBeEmpty();
});

it('fail: mcp peer=laravel/mcp installed=False compatible=True mode=fail boots failed [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', false, true, 'fail');
    expect($c['threw'])->not->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['artifacts'])->toBeEmpty();
});

it('fail: mcp peer=laravel/mcp installed=False compatible=True mode=fail never half-registers [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', false, true, 'fail');
    expect($c['registers'])->toBeFalse()->and($c['artifacts'])->toBeEmpty();
});

it('edge: mcp peer=laravel/mcp installed=False compatible=True mode=disable soft-disables [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', false, true, 'disable');
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['status']->status)->toBe(PeerSurfaceStatus::DISABLED_INCOMPATIBLE)
        ->and($c['status']->logs)->not->toBeEmpty();
});

it('fail: mcp peer=laravel/mcp installed=False compatible=True mode=disable never half-registers [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', false, true, 'disable');
    expect($c['registers'])->toBeFalse()->and($c['artifacts'])->toBeEmpty();
});

it('fail: mcp peer=laravel/mcp installed=False compatible=False mode=fail boots failed [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', false, false, 'fail');
    expect($c['threw'])->not->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['artifacts'])->toBeEmpty();
});

it('fail: mcp peer=laravel/mcp installed=False compatible=False mode=fail never half-registers [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', false, false, 'fail');
    expect($c['registers'])->toBeFalse()->and($c['artifacts'])->toBeEmpty();
});

it('edge: mcp peer=laravel/mcp installed=False compatible=False mode=disable soft-disables [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', false, false, 'disable');
    expect($c['threw'])->toBeNull()
        ->and($c['registers'])->toBeFalse()
        ->and($c['status']->status)->toBe(PeerSurfaceStatus::DISABLED_INCOMPATIBLE)
        ->and($c['status']->logs)->not->toBeEmpty();
});

it('fail: mcp peer=laravel/mcp installed=False compatible=False mode=disable never half-registers [D-011]', function () {
    $c = BootHelpers::peerCell('mcp', false, false, 'disable');
    expect($c['registers'])->toBeFalse()->and($c['artifacts'])->toBeEmpty();
});
