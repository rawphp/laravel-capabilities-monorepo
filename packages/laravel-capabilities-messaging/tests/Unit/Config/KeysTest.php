<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('happy: messaging config has key telegram.enabled [MSG-001]', function () {
    expect(H::config()->hasKey('telegram.enabled'))->toBeTrue();
});

it('happy: messaging config has key telegram.bot_token [MSG-001]', function () {
    expect(H::config()->hasKey('telegram.bot_token'))->toBeTrue();
});

it('happy: messaging config has key telegram.webhook_secret [MSG-001]', function () {
    expect(H::config()->hasKey('telegram.webhook_secret'))->toBeTrue();
});

it('happy: messaging config has key telegram.callback_ttl_seconds [MSG-001]', function () {
    expect(H::config()->hasKey('telegram.callback_ttl_seconds'))->toBeTrue();
});

it('happy: messaging config has key agent_profile [MSG-001]', function () {
    expect(H::config()->hasKey('agent_profile'))->toBeTrue();
});

it('happy: messaging config has key identity.mode [MSG-001]', function () {
    expect(H::config()->hasKey('identity.mode'))->toBeTrue();
});
