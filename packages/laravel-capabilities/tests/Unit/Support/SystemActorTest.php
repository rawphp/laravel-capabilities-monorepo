<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\SystemActor;

it('happy: named factory sets name [D-002]', function () {
    $actor = SystemActor::named('scheduler');

    expect($actor)->toBeInstanceOf(SystemActor::class)
        ->and($actor->name)->toBe('scheduler');
});

it('happy: equality by name for allowlists [D-002]', function () {
    $a = SystemActor::named('scheduler');
    $b = SystemActor::named('scheduler');
    $c = SystemActor::named('reconciliation');

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse()
        ->and($a->name)->toBe($b->name);
});

it('fail: empty name rejected [D-002]', function () {
    expect(fn () => SystemActor::named(''))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => SystemActor::named('   '))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new SystemActor(''))
        ->toThrow(InvalidArgumentException::class);
});

it('edge: name is readonly after construct [D-002]', function () {
    $actor = SystemActor::named('horizon');
    $prop = new ReflectionProperty(SystemActor::class, 'name');

    expect($prop->isReadOnly())->toBeTrue()
        ->and($actor->name)->toBe('horizon');
});
