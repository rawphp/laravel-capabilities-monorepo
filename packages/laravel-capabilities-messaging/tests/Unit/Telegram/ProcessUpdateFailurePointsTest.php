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



it("fail: process update handles failure at invalid_update_shape without domain bypass [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $registry = new FakeCapabilityBus();
    $p = H::processor(['identity' => $identity, 'registry' => $registry]);
    $update = H::telegramUpdate(userId: 42);
    if ('invalid_update_shape' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('invalid_update_shape' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'invalid_update_shape']);
    expect($r['ok'])->toBeFalse();
    expect($r['domain_bypass'] ?? false)->toBeFalse();
    expect($registry->invokeCount())->toBe(0);
});

it("edge: process update failure at invalid_update_shape is observable in logs or failed jobs [D-019]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $update = H::telegramUpdate(userId: 42);
    if ('invalid_update_shape' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('invalid_update_shape' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'invalid_update_shape']);
    expect(($r['observable'] ?? false) || $p->logs() !== [] || isset($r['tags']) || isset($r['error']))->toBeTrue();
});


it("fail: process update handles failure at unknown_chat without domain bypass [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $registry = new FakeCapabilityBus();
    $p = H::processor(['identity' => $identity, 'registry' => $registry]);
    $update = H::telegramUpdate(userId: 42);
    if ('unknown_chat' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('unknown_chat' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'unknown_chat']);
    expect($r['ok'])->toBeFalse();
    expect($r['domain_bypass'] ?? false)->toBeFalse();
    expect($registry->invokeCount())->toBe(0);
});

it("edge: process update failure at unknown_chat is observable in logs or failed jobs [D-019]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $update = H::telegramUpdate(userId: 42);
    if ('unknown_chat' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('unknown_chat' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'unknown_chat']);
    expect(($r['observable'] ?? false) || $p->logs() !== [] || isset($r['tags']) || isset($r['error']))->toBeTrue();
});


it("fail: process update handles failure at identity_unresolved without domain bypass [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $registry = new FakeCapabilityBus();
    $p = H::processor(['identity' => $identity, 'registry' => $registry]);
    $update = H::telegramUpdate(userId: 42);
    if ('identity_unresolved' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('identity_unresolved' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'identity_unresolved']);
    expect($r['ok'])->toBeFalse();
    expect($r['domain_bypass'] ?? false)->toBeFalse();
    expect($registry->invokeCount())->toBe(0);
});

it("edge: process update failure at identity_unresolved is observable in logs or failed jobs [D-019]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $update = H::telegramUpdate(userId: 42);
    if ('identity_unresolved' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('identity_unresolved' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'identity_unresolved']);
    expect(($r['observable'] ?? false) || $p->logs() !== [] || isset($r['tags']) || isset($r['error']))->toBeTrue();
});


it("fail: process update handles failure at thread_store_failure without domain bypass [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $registry = new FakeCapabilityBus();
    $p = H::processor(['identity' => $identity, 'registry' => $registry]);
    $update = H::telegramUpdate(userId: 42);
    if ('thread_store_failure' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('thread_store_failure' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'thread_store_failure']);
    expect($r['ok'])->toBeFalse();
    expect($r['domain_bypass'] ?? false)->toBeFalse();
    expect($registry->invokeCount())->toBe(0);
});

it("edge: process update failure at thread_store_failure is observable in logs or failed jobs [D-019]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $update = H::telegramUpdate(userId: 42);
    if ('thread_store_failure' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('thread_store_failure' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'thread_store_failure']);
    expect(($r['observable'] ?? false) || $p->logs() !== [] || isset($r['tags']) || isset($r['error']))->toBeTrue();
});


it("fail: process update handles failure at ingress_failure without domain bypass [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $registry = new FakeCapabilityBus();
    $p = H::processor(['identity' => $identity, 'registry' => $registry]);
    $update = H::telegramUpdate(userId: 42);
    if ('ingress_failure' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('ingress_failure' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'ingress_failure']);
    expect($r['ok'])->toBeFalse();
    expect($r['domain_bypass'] ?? false)->toBeFalse();
    expect($registry->invokeCount())->toBe(0);
});

it("edge: process update failure at ingress_failure is observable in logs or failed jobs [D-019]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $update = H::telegramUpdate(userId: 42);
    if ('ingress_failure' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('ingress_failure' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'ingress_failure']);
    expect(($r['observable'] ?? false) || $p->logs() !== [] || isset($r['tags']) || isset($r['error']))->toBeTrue();
});


it("fail: process update handles failure at agent_failure without domain bypass [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $registry = new FakeCapabilityBus();
    $p = H::processor(['identity' => $identity, 'registry' => $registry]);
    $update = H::telegramUpdate(userId: 42);
    if ('agent_failure' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('agent_failure' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'agent_failure']);
    expect($r['ok'])->toBeFalse();
    expect($r['domain_bypass'] ?? false)->toBeFalse();
    expect($registry->invokeCount())->toBe(0);
});

it("edge: process update failure at agent_failure is observable in logs or failed jobs [D-019]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $update = H::telegramUpdate(userId: 42);
    if ('agent_failure' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('agent_failure' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'agent_failure']);
    expect(($r['observable'] ?? false) || $p->logs() !== [] || isset($r['tags']) || isset($r['error']))->toBeTrue();
});


it("fail: process update handles failure at tool_registry_failure without domain bypass [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $registry = new FakeCapabilityBus();
    $p = H::processor(['identity' => $identity, 'registry' => $registry]);
    $update = H::telegramUpdate(userId: 42);
    if ('tool_registry_failure' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('tool_registry_failure' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'tool_registry_failure']);
    expect($r['ok'])->toBeFalse();
    expect($r['domain_bypass'] ?? false)->toBeFalse();
    expect($registry->invokeCount())->toBe(0);
});

it("edge: process update failure at tool_registry_failure is observable in logs or failed jobs [D-019]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $update = H::telegramUpdate(userId: 42);
    if ('tool_registry_failure' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('tool_registry_failure' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'tool_registry_failure']);
    expect(($r['observable'] ?? false) || $p->logs() !== [] || isset($r['tags']) || isset($r['error']))->toBeTrue();
});


it("fail: process update handles failure at reply_failure without domain bypass [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $registry = new FakeCapabilityBus();
    $p = H::processor(['identity' => $identity, 'registry' => $registry]);
    $update = H::telegramUpdate(userId: 42);
    if ('reply_failure' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('reply_failure' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'reply_failure']);
    expect($r['ok'])->toBeFalse();
    expect($r['domain_bypass'] ?? false)->toBeFalse();
    expect($registry->invokeCount())->toBe(0);
});

it("edge: process update failure at reply_failure is observable in logs or failed jobs [D-019]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $update = H::telegramUpdate(userId: 42);
    if ('reply_failure' === 'invalid_update_shape') {
        $update = ['garbage' => true];
    }
    if ('reply_failure' === 'unknown_chat') {
        $update = ['update_id' => 1, 'message' => ['from' => ['id' => 42], 'text' => 'x']];
    }
    $r = $p->runPipeline($update, ['fail_at' => 'reply_failure']);
    expect(($r['observable'] ?? false) || $p->logs() !== [] || isset($r['tags']) || isset($r['error']))->toBeTrue();
});
