<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Support\FakeTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;



it("happy: step order 00 verify_secret [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'])->toContain('verify_webhook_secret');
    $idx = array_search('verify_webhook_secret', $r['steps'], true);
    expect($idx)->toBeInt();
});


it("happy: step order 01 queue [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'])->toContain('queue_process_update');
    $idx = array_search('queue_process_update', $r['steps'], true);
    expect($idx)->toBeInt();
});


it("happy: step order 02 resolve_identity [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'])->toContain('resolve_identity');
    $idx = array_search('resolve_identity', $r['steps'], true);
    expect($idx)->toBeInt();
});


it("happy: step order 03 map_thread [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'])->toContain('map_thread');
    $idx = array_search('map_thread', $r['steps'], true);
    expect($idx)->toBeInt();
});


it("happy: step order 04 ingress [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'])->toContain('conversation_ingress');
    $idx = array_search('conversation_ingress', $r['steps'], true);
    expect($idx)->toBeInt();
});


it("happy: step order 05 agent [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'])->toContain('agent_tools_profile');
    $idx = array_search('agent_tools_profile', $r['steps'], true);
    expect($idx)->toBeInt();
});


it("happy: step order 06 tools [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'])->toContain('tool_calls_registry');
    $idx = array_search('tool_calls_registry', $r['steps'], true);
    expect($idx)->toBeInt();
});


it("happy: step order 07 reply [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'])->toContain('conversation_reply');
    $idx = array_search('conversation_reply', $r['steps'], true);
    expect($idx)->toBeInt();
});


it("fail: tools not reached if verify_secret fails [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['secret_valid' => false]);
    expect($r['tools_reached'] ?? false)->toBeFalse();
    expect(in_array('tool_calls_registry', $r['steps'] ?? [], true))->toBeFalse();
});

it("fail: tools not reached if resolve_identity fails [MSG-003]", function () {
    $p = H::processor(); // unlinked
    $r = $p->runPipeline(H::telegramUpdate(userId: 999));
    expect(in_array('tool_calls_registry', $r['steps'] ?? [], true))->toBeFalse();
});
