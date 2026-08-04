<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('edge: thread isolation for topic=null [MSG-004]', function () {
    $s = H::threads();
    $topic = null;
    $t = $s->getOrCreate('chat-1', $topic);
    $s->appendHistory($t['id'], ['topic' => 'null']);
    expect($s->history($t['id'])[0]['topic'])->toBe('null');
});

it('fail: topic=null cannot read other topic history [MSG-004]', function () {
    $s = H::threads();
    $topic = null;
    $other = 1;
    $a = $s->getOrCreate('chat-1', $topic);
    $b = $s->getOrCreate('chat-1', $other);
    $s->appendHistory($a['id'], ['secret' => 'null']);
    expect($s->history($b['id']))->toBeEmpty();
});

it('edge: thread isolation for topic=1 [MSG-004]', function () {
    $s = H::threads();
    $topic = 1;
    $t = $s->getOrCreate('chat-1', $topic);
    $s->appendHistory($t['id'], ['topic' => '1']);
    expect($s->history($t['id'])[0]['topic'])->toBe('1');
});

it('fail: topic=1 cannot read other topic history [MSG-004]', function () {
    $s = H::threads();
    $topic = 1;
    $other = 2;
    $a = $s->getOrCreate('chat-1', $topic);
    $b = $s->getOrCreate('chat-1', $other);
    $s->appendHistory($a['id'], ['secret' => '1']);
    expect($s->history($b['id']))->toBeEmpty();
});

it('edge: thread isolation for topic=2 [MSG-004]', function () {
    $s = H::threads();
    $topic = 2;
    $t = $s->getOrCreate('chat-1', $topic);
    $s->appendHistory($t['id'], ['topic' => '2']);
    expect($s->history($t['id'])[0]['topic'])->toBe('2');
});

it('fail: topic=2 cannot read other topic history [MSG-004]', function () {
    $s = H::threads();
    $topic = 2;
    $other = 1;
    $a = $s->getOrCreate('chat-1', $topic);
    $b = $s->getOrCreate('chat-1', $other);
    $s->appendHistory($a['id'], ['secret' => '2']);
    expect($s->history($b['id']))->toBeEmpty();
});

it('edge: thread isolation for topic=general [MSG-004]', function () {
    $s = H::threads();
    $topic = 'general';
    $t = $s->getOrCreate('chat-1', $topic);
    $s->appendHistory($t['id'], ['topic' => 'general']);
    expect($s->history($t['id'])[0]['topic'])->toBe('general');
});

it('fail: topic=general cannot read other topic history [MSG-004]', function () {
    $s = H::threads();
    $topic = 'general';
    $other = 'other';
    $a = $s->getOrCreate('chat-1', $topic);
    $b = $s->getOrCreate('chat-1', $other);
    $s->appendHistory($a['id'], ['secret' => 'general']);
    expect($s->history($b['id']))->toBeEmpty();
});
