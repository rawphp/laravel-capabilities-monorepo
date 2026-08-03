<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\MessagingServiceProvider;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('happy: telegram channel config reads env keys [MSG-001]', function () {
    $cfg = H::config(['telegram' => ['bot_token' => 'tok', 'webhook_secret' => 'sec']]);
    expect($cfg->botToken())->toBe('tok')->and($cfg->webhookSecret())->toBe('sec');
});

it('happy: agent profile name required in messaging config for bot [D-008]', function () {
    expect(H::config()->requireAgentProfile())->toBe('support');
});

it('edge: telegram enabled false registers no webhook routes [MSG-001]', function () {
    $plan = MessagingServiceProvider::registrationPlan(['telegram' => ['enabled' => false]]);
    expect($plan['routes'])->toBeEmpty();
});

it('fail: missing agent profile name fails loudly on first bot traffic [D-008]', function () {
    $cfg = H::config(['agent_profile' => '']);
    expect(fn () => $cfg->requireAgentProfile())->toThrow(RuntimeException::class);
});

it('happy: telegram channel switch independent of core messaging surface [MSG-001]', function () {
    // Channel can be on while tests do not require core surfaces.messaging
    $plan = MessagingServiceProvider::registrationPlan(['telegram' => ['enabled' => true]]);
    expect($plan['telegram_enabled'])->toBeTrue()->and($plan['routes'])->toContain('telegram.webhook');
});

it('edge: webhook secret config key present [MSG-001]', function () {
    expect(array_key_exists('webhook_secret', MessagingConfig::defaults()['telegram']))->toBeTrue();
});

it('edge: bot token config key present [MSG-001]', function () {
    expect(array_key_exists('bot_token', MessagingConfig::defaults()['telegram']))->toBeTrue();
});
