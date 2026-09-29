<?php

declare(strict_types=1);

// ToolSelection value object.

use Rawphp\Capabilities\Adapters\ToolSelection;

it('ToolSelection::of and the constructor both keep the profile as given', function () {
    $a = ToolSelection::of('ops');
    $b = new ToolSelection(['groups' => ['finance']]);
    expect($a->profile)->toBe('ops')->and($b->profile)->toBe(['groups' => ['finance']]);
});
