<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Support\TelegramText;

it('counts UTF-16 code units like the Bot API: an emoji outside the BMP is two', function () {
    expect(TelegramText::length('abc'))->toBe(3)
        ->and(TelegramText::length('é'))->toBe(1)
        ->and(TelegramText::length('😀'))->toBe(2);
});

it('leaves text within the limit alone and cuts longer text to the limit, ellipsis included', function () {
    expect(TelegramText::truncate('short', 10))->toBe('short')
        ->and(TelegramText::truncate('abcdefghijk', 10))->toBe('abcdefghi…')
        // The emoji would cross the limit, so it is dropped whole, never split into a lone surrogate.
        ->and(TelegramText::truncate('abcdefgh😀zz', 10))->toBe('abcdefgh…');
});

it('splits a long text into parts within the limit, at a paragraph break when there is one [M-204]', function () {
    $first = str_repeat('a', 3000);
    $second = str_repeat('b', 3000);

    expect(TelegramText::split($first."\n\n".$second))->toBe([$first, $second])
        ->and(TelegramText::split('short'))->toBe(['short']);
});

it('falls back to a line break, then a space, then a hard cut [M-204]', function () {
    expect(TelegramText::split("aaaaaa\nbbbb cc", 10))->toBe(['aaaaaa', 'bbbb cc'])
        ->and(TelegramText::split('aaaaaaa bbbbbbb', 10))->toBe(['aaaaaaa', 'bbbbbbb'])
        ->and(TelegramText::split(str_repeat('x', 25), 10))->toBe([str_repeat('x', 10), str_repeat('x', 10), str_repeat('x', 5)]);
});

it('never splits an emoji and keeps every part within the UTF-16 limit [M-204]', function () {
    $parts = TelegramText::split(str_repeat('😀', 5000));

    expect($parts)->toHaveCount(3)
        ->and(array_map(TelegramText::length(...), $parts))->each->toBeLessThanOrEqual(TelegramText::MAX_LENGTH)
        ->and(implode('', $parts))->toBe(str_repeat('😀', 5000));
});

it('has nothing to send for empty or blank text [M-204]', function () {
    expect(TelegramText::split(''))->toBe([])
        ->and(TelegramText::split(" \n "))->toBe([]);
});
