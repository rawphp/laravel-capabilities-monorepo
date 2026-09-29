<?php

declare(strict_types=1);

// PeerSurfaceStatus::isUp.

use Rawphp\Capabilities\Adapters\PeerSurfaceStatus;

it('PeerSurfaceStatus::isUp is true only for an up surface', function () {
    $up = new PeerSurfaceStatus('agent', PeerSurfaceStatus::UP, true);
    $down = new PeerSurfaceStatus('mcp', PeerSurfaceStatus::DISABLED_CONFIG, false, reason: 'off');
    expect($up->isUp())->toBeTrue()->and($down->isUp())->toBeFalse();
});
