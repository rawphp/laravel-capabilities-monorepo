<?php

declare(strict_types=1);

// InvokeState stage tracking.

use Rawphp\Capabilities\Pipeline\InvokeState;
use Rawphp\Capabilities\Registry\CapabilityDefinition;

it('InvokeState tracks marked stages, their index, and the request id', function () {
    $def = new CapabilityDefinition(name: 's', description: 'd', readOnly: true);
    $state = new InvokeState($def, ['a' => 1], 'http', ['x' => true], 'req-1');
    $state->mark('a');
    $state->mark('b');
    expect($state->hasStage('a'))->toBeTrue()
        ->and($state->hasStage('z'))->toBeFalse()
        ->and($state->stageIndex('b'))->toBe(1)
        ->and($state->stageIndex('missing'))->toBeNull()
        ->and($state->requestId)->toBe('req-1');
});
