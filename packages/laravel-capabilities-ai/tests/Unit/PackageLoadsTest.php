<?php

declare(strict_types=1);

use Rawphp\CapabilitiesAi\Package;

it('loads the package namespace', function () {
    expect(class_exists(Package::class))->toBeTrue();
    expect(Package::name())->toBe('rawphp/laravel-capabilities-ai');
});
