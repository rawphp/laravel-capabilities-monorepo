<?php

// REQ-010 fleshed unit tests for Profiles/GroupsTagsTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ProfileHelpers;

it('happy: group finance composes tools from capability groups [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['groups' => ['finance']]), 'name');
    expect($names)->not->toBeEmpty();
});

it('edge: tag finance can contribute to selection [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['tags' => ['finance']]), 'name');
    expect($names)->not->toBeEmpty();
});

it('fail: group finance still filtered by canDiscover [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [
            ['name' => 'visible-cap', 'groups' => ['finance'], 'canDiscover' => true],
            ['name' => 'hidden-cap', 'groups' => ['finance'], 'canDiscover' => false],
        ],
    ]);
    $names = array_column($h['registry']->aiTools(['groups' => ['finance']]), 'name');
    expect($names)->toContain('visible-cap')->and($names)->not->toContain('hidden-cap');
});

it('happy: group support composes tools from capability groups [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['groups' => ['support']]), 'name');
    expect($names)->not->toBeEmpty();
});

it('edge: tag support can contribute to selection [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['tags' => ['support']]), 'name');
    expect($names)->not->toBeEmpty();
});

it('fail: group support still filtered by canDiscover [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [
            ['name' => 'visible-cap', 'groups' => ['support'], 'canDiscover' => true],
            ['name' => 'hidden-cap', 'groups' => ['support'], 'canDiscover' => false],
        ],
    ]);
    $names = array_column($h['registry']->aiTools(['groups' => ['support']]), 'name');
    expect($names)->toContain('visible-cap')->and($names)->not->toContain('hidden-cap');
});

it('happy: group ops composes tools from capability groups [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['groups' => ['ops']]), 'name');
    expect($names)->not->toBeEmpty();
});

it('edge: tag ops can contribute to selection [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['tags' => ['ops']]), 'name');
    expect($names)->not->toBeEmpty();
});

it('fail: group ops still filtered by canDiscover [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [
            ['name' => 'visible-cap', 'groups' => ['ops'], 'canDiscover' => true],
            ['name' => 'hidden-cap', 'groups' => ['ops'], 'canDiscover' => false],
        ],
    ]);
    $names = array_column($h['registry']->aiTools(['groups' => ['ops']]), 'name');
    expect($names)->toContain('visible-cap')->and($names)->not->toContain('hidden-cap');
});

it('happy: group billing composes tools from capability groups [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['groups' => ['billing']]), 'name');
    expect($names)->not->toBeEmpty();
});

it('edge: tag billing can contribute to selection [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();
    $names = array_column($h['registry']->aiTools(['tags' => ['billing']]), 'name');
    expect($names)->not->toBeEmpty();
});

it('fail: group billing still filtered by canDiscover [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [
            ['name' => 'visible-cap', 'groups' => ['billing'], 'canDiscover' => true],
            ['name' => 'hidden-cap', 'groups' => ['billing'], 'canDiscover' => false],
        ],
    ]);
    $names = array_column($h['registry']->aiTools(['groups' => ['billing']]), 'name');
    expect($names)->toContain('visible-cap')->and($names)->not->toContain('hidden-cap');
});
