<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\CapabilitiesMessaging\MessagingServiceProvider;
use Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('happy: registers messaging config [MSG-001]', function () {
    $plan = MessagingServiceProvider::registrationPlan();
    expect($plan['config_merged'])->toBeTrue()
        ->and($plan['config_keys'])->toContain('telegram')
        ->and($plan['config_keys'])->toContain('agent_profile');
});

it('edge: registers webhook routes when telegram enabled [MSG-001]', function () {
    $plan = MessagingServiceProvider::registrationPlan(['telegram' => ['enabled' => true]]);
    expect($plan['routes'])->toContain('telegram.webhook');
});

it('fail: registers no webhook routes when telegram disabled [MSG-001]', function () {
    $plan = MessagingServiceProvider::registrationPlan(['telegram' => ['enabled' => false]]);
    expect($plan['routes'])->toBeEmpty();
});

it('happy: binds ApprovalNotifier implementation [D-006]', function () {
    $plan = MessagingServiceProvider::registrationPlan();
    expect($plan['bindings'])->toContain(TelegramApprovalNotifier::class);
    expect(H::notifier())->toBeInstanceOf(ApprovalNotifier::class);
});
