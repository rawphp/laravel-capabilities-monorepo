<?php

declare(strict_types=1);

// DiscoveryPaths default and config resolution.

use Rawphp\Capabilities\Discovery\DiscoveryPaths;

it('DiscoveryPaths::fromConfig falls back to the default path and accepts a string or list of paths', function () {
    $default = DiscoveryPaths::default();
    expect($default)->toBeString()->not->toBe('');
    expect(DiscoveryPaths::fromConfig([]))->toBe([$default]);
    expect(DiscoveryPaths::fromConfig(['path' => '/tmp/a']))->toBe(['/tmp/a']);
    expect(DiscoveryPaths::fromConfig(['path' => ['/a', '/b']]))->toBe(['/a', '/b']);
});
