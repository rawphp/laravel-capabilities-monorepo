<?php

declare(strict_types=1);

// LogFallbackMetrics enabled and disabled.

use Rawphp\Capabilities\Observability\LogFallbackMetrics;

it('LogFallbackMetrics records nothing when disabled and counts and logs when enabled', function () {
    $off = new LogFallbackMetrics(false);
    $off->increment('x');
    $off->histogram('y', 1.0);
    expect($off->enabled())->toBeFalse()->and($off->logLines())->toBe([]);

    $on = new LogFallbackMetrics(true);
    $on->increment('calls', 2, ['c' => 'http']);
    $on->histogram('latency', 12.5, ['c' => 'http']);
    expect($on->get('calls', ['c' => 'http']))->toBe(2)
        ->and($on->logLines())->not->toBeEmpty()
        ->and($on->inner())->not->toBeNull();
});
