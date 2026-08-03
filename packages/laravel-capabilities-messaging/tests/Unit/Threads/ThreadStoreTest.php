<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('happy: creates and retrieves thread by chat id [MSG-004]', function () {
    $s = H::threads();
    $t = $s->getOrCreate('100');
    expect($s->find('100')['id'])->toBe($t['id']);
});

it('happy: appends history for agent turn [MSG-004]', function () {
    $s = H::threads();
    $t = $s->getOrCreate('100');
    $s->appendHistory($t['id'], ['role' => 'user', 'text' => 'hi']);
    expect($s->history($t['id']))->toHaveCount(1);
});

it('edge: topic threads isolated per topic id [MSG-004]', function () {
    $s = H::threads();
    $a = $s->getOrCreate('100', 1);
    $b = $s->getOrCreate('100', 2);
    $s->appendHistory($a['id'], ['text' => 'a']);
    expect($s->history($b['id']))->toBeEmpty();
    expect($a['id'])->not->toBe($b['id']);
});

it('happy: maps chat topic to conversation thread id [MSG-004]', function () {
    $s = H::threads();
    expect($s->threadIdFor('c1', 'general'))->toBe('tg:c1:general');
});

it('fail: unknown chat without create policy does not leak other threads [MSG-004]', function () {
    $s = H::threads();
    $t = $s->getOrCreate('known');
    $s->appendHistory($t['id'], ['secret' => true]);
    expect($s->historyForChat('unknown', null, create: false))->toBeEmpty();
});

it('edge: history append is ordered [MSG-004]', function () {
    $s = H::threads();
    $t = $s->getOrCreate('100');
    $s->appendHistory($t['id'], ['n' => 1]);
    $s->appendHistory($t['id'], ['n' => 2]);
    expect(array_column($s->history($t['id']), 'n'))->toBe([1, 2]);
});
