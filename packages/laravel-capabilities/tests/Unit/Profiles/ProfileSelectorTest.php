<?php

// ProfileSelector: resolving selection shapes and matching definitions against them.

declare(strict_types=1);

use Rawphp\Capabilities\Profiles\ProfileSelector;
use Rawphp\Capabilities\Registry\CapabilityDefinition;

function profileSelectorDefinition(): CapabilityDefinition
{
    return new CapabilityDefinition(
        name: 'p.cap',
        description: 'd',
        aliases: ['p.alias'],
        groups: ['finance'],
        tags: ['billing'],
        readOnly: true,
    );
}

it('happy: an only-list selection resolves and matches a listed capability name', function () {
    $sel = new ProfileSelector;

    $only = $sel->resolve(['only' => ['p.cap', 'other']]);

    expect($only['kind'])->toBeString()
        ->and($sel->matches(profileSelectorDefinition(), $only))->toBeTrue();
});

it('happy: a groups selection matches a capability in that group', function () {
    $sel = new ProfileSelector;

    expect($sel->matches(profileSelectorDefinition(), $sel->resolve(['groups' => ['finance']])))->toBeTrue();
});

it('happy: a tags selection matches a capability carrying that tag', function () {
    $sel = new ProfileSelector;

    expect($sel->matches(profileSelectorDefinition(), $sel->resolve(['tags' => ['billing']])))->toBeTrue();
});

it('happy: a bare list selection resolves to kind only and matches', function () {
    $sel = new ProfileSelector;

    $list = $sel->resolve(['p.cap', 'x']);

    expect($list['kind'])->toBe('only')
        ->and($sel->matches(profileSelectorDefinition(), $list))->toBeTrue();
});

it('edge: a profile combined with only and groups resolves with an allowlist', function () {
    $conflict = (new ProfileSelector)->resolve([
        'profile' => 'ops',
        'only' => ['p.cap'],
        'groups' => ['finance'],
    ]);

    expect($conflict)->toHaveKey('allowlist');
});

it('edge: a profile combined with a non-intersecting only list still carries an allowlist', function () {
    $emptyOnlyIntersect = (new ProfileSelector)->resolve([
        'only' => ['nope'],
        'profile' => 'ops',
    ]);

    expect($emptyOnlyIntersect['allowlist'] ?? null)->not->toBeNull();
});

it('fail: a null selection matches nothing', function () {
    $sel = new ProfileSelector;

    expect($sel->matches(profileSelectorDefinition(), $sel->resolve(null)))->toBeFalse();
});

it('fail: an only-list without the capability name does not match', function () {
    $sel = new ProfileSelector;

    expect($sel->matches(profileSelectorDefinition(), $sel->resolve(['only' => ['zzz']])))->toBeFalse();
});

it('fail: a groups selection for another group does not match', function () {
    $sel = new ProfileSelector;

    expect($sel->matches(profileSelectorDefinition(), $sel->resolve(['groups' => ['other']])))->toBeFalse();
});
