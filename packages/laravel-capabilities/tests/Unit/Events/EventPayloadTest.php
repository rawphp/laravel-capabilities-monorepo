<?php

declare(strict_types=1);

// EventPayload helpers.

use Rawphp\Capabilities\Events\CapabilityApprovalDecided;
use Rawphp\Capabilities\Events\CapabilityApprovalExecuted;
use Rawphp\Capabilities\Events\CapabilityApprovalRequested;
use Rawphp\Capabilities\Events\CapabilityFailed;
use Rawphp\Capabilities\Events\CapabilityInvoked;
use Rawphp\Capabilities\Events\EventPayload;

it('EventPayload::meta keeps custom keys and the after-commit event list drives listenersShouldUseAfterCommit', function () {
    $meta = EventPayload::meta([
        'name' => 'x',
        'caller' => 'http',
        'custom' => 1,
    ]);
    expect($meta['name'])->toBe('x')
        ->and($meta['custom'])->toBe(1)
        ->and(EventPayload::hasCorrelationKey('invocation_id'))->toBeTrue()
        ->and(EventPayload::hasCorrelationKey('nope'))->toBeFalse();

    $after = EventPayload::afterCommitEvents();
    expect($after)->toContain(CapabilityInvoked::class)
        ->and($after)->toContain(CapabilityFailed::class)
        ->and($after)->toContain(CapabilityApprovalRequested::class)
        ->and($after)->toContain(CapabilityApprovalDecided::class)
        ->and($after)->toContain(CapabilityApprovalExecuted::class);

    expect(EventPayload::listenersShouldUseAfterCommit(CapabilityInvoked::class))->toBeTrue()
        ->and(EventPayload::listenersShouldUseAfterCommit('Nonexistent\\Event'))->toBeFalse()
        ->and(EventPayload::listenersShouldUseAfterCommit(stdClass::class))->toBeFalse();
});
