<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('happy: webhook secret valid accepted [MSG-003]', function () {
    $r = H::webhook()->handle(
        ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'],
        H::telegramUpdate(),
    );
    expect($r['ok'])->toBeTrue();
});

it('fail: webhook secret invalid rejected [MSG-003]', function () {
    $r = H::webhook()->handle(['X-Telegram-Bot-Api-Secret-Token' => 'nope'], H::telegramUpdate());
    expect($r['ok'])->toBeFalse()->and($r['status'])->toBe(401);
});

it('fail: webhook secret missing rejected [MSG-003]', function () {
    $r = H::webhook()->handle([], H::telegramUpdate());
    expect($r['ok'])->toBeFalse();
});

it('fail: webhook secret empty rejected [MSG-003]', function () {
    $r = H::webhook()->handle(['X-Telegram-Bot-Api-Secret-Token' => ''], H::telegramUpdate());
    expect($r['ok'])->toBeFalse();
});
