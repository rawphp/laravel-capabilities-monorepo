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
