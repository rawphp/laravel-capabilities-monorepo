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



it("happy: pipeline step verify_webhook_secret executes in order [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'] ?? [])->toContain('verify_webhook_secret');
});

it("fail: pipeline aborts before tools when step verify_webhook_secret fails if prior required [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $failAt = 'verify_webhook_secret';
    if ($failAt === 'verify_webhook_secret') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['secret_valid' => false]);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    if ($failAt === 'queue_process_update') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => 'queue_process_update']);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    $map = [
        'resolve_identity' => 'identity_unresolved',
        'map_thread' => 'thread_store_failure',
        'conversation_ingress' => 'ingress_failure',
        'agent_tools_profile' => 'profile_missing',
        'tool_calls_registry' => 'tool_registry_failure',
        'conversation_reply' => 'reply_failure',
    ];
    $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => $map[$failAt] ?? $failAt]);
    expect($r['ok'])->toBeFalse();
    if ($failAt !== 'tool_calls_registry' && $failAt !== 'conversation_reply' && $failAt !== 'agent_tools_profile') {
        expect(in_array('tool_calls_registry', $r['steps'] ?? [], true))->toBeFalse();
    }
});


it("happy: pipeline step queue_process_update executes in order [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'] ?? [])->toContain('queue_process_update');
});

it("fail: pipeline aborts before tools when step queue_process_update fails if prior required [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $failAt = 'queue_process_update';
    if ($failAt === 'verify_webhook_secret') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['secret_valid' => false]);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    if ($failAt === 'queue_process_update') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => 'queue_process_update']);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    $map = [
        'resolve_identity' => 'identity_unresolved',
        'map_thread' => 'thread_store_failure',
        'conversation_ingress' => 'ingress_failure',
        'agent_tools_profile' => 'profile_missing',
        'tool_calls_registry' => 'tool_registry_failure',
        'conversation_reply' => 'reply_failure',
    ];
    $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => $map[$failAt] ?? $failAt]);
    expect($r['ok'])->toBeFalse();
    if ($failAt !== 'tool_calls_registry' && $failAt !== 'conversation_reply' && $failAt !== 'agent_tools_profile') {
        expect(in_array('tool_calls_registry', $r['steps'] ?? [], true))->toBeFalse();
    }
});


it("happy: pipeline step resolve_identity executes in order [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'] ?? [])->toContain('resolve_identity');
});

it("fail: pipeline aborts before tools when step resolve_identity fails if prior required [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $failAt = 'resolve_identity';
    if ($failAt === 'verify_webhook_secret') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['secret_valid' => false]);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    if ($failAt === 'queue_process_update') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => 'queue_process_update']);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    $map = [
        'resolve_identity' => 'identity_unresolved',
        'map_thread' => 'thread_store_failure',
        'conversation_ingress' => 'ingress_failure',
        'agent_tools_profile' => 'profile_missing',
        'tool_calls_registry' => 'tool_registry_failure',
        'conversation_reply' => 'reply_failure',
    ];
    $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => $map[$failAt] ?? $failAt]);
    expect($r['ok'])->toBeFalse();
    if ($failAt !== 'tool_calls_registry' && $failAt !== 'conversation_reply' && $failAt !== 'agent_tools_profile') {
        expect(in_array('tool_calls_registry', $r['steps'] ?? [], true))->toBeFalse();
    }
});


it("happy: pipeline step map_thread executes in order [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'] ?? [])->toContain('map_thread');
});

it("fail: pipeline aborts before tools when step map_thread fails if prior required [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $failAt = 'map_thread';
    if ($failAt === 'verify_webhook_secret') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['secret_valid' => false]);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    if ($failAt === 'queue_process_update') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => 'queue_process_update']);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    $map = [
        'resolve_identity' => 'identity_unresolved',
        'map_thread' => 'thread_store_failure',
        'conversation_ingress' => 'ingress_failure',
        'agent_tools_profile' => 'profile_missing',
        'tool_calls_registry' => 'tool_registry_failure',
        'conversation_reply' => 'reply_failure',
    ];
    $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => $map[$failAt] ?? $failAt]);
    expect($r['ok'])->toBeFalse();
    if ($failAt !== 'tool_calls_registry' && $failAt !== 'conversation_reply' && $failAt !== 'agent_tools_profile') {
        expect(in_array('tool_calls_registry', $r['steps'] ?? [], true))->toBeFalse();
    }
});


it("happy: pipeline step conversation_ingress executes in order [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'] ?? [])->toContain('conversation_ingress');
});

it("fail: pipeline aborts before tools when step conversation_ingress fails if prior required [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $failAt = 'conversation_ingress';
    if ($failAt === 'verify_webhook_secret') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['secret_valid' => false]);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    if ($failAt === 'queue_process_update') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => 'queue_process_update']);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    $map = [
        'resolve_identity' => 'identity_unresolved',
        'map_thread' => 'thread_store_failure',
        'conversation_ingress' => 'ingress_failure',
        'agent_tools_profile' => 'profile_missing',
        'tool_calls_registry' => 'tool_registry_failure',
        'conversation_reply' => 'reply_failure',
    ];
    $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => $map[$failAt] ?? $failAt]);
    expect($r['ok'])->toBeFalse();
    if ($failAt !== 'tool_calls_registry' && $failAt !== 'conversation_reply' && $failAt !== 'agent_tools_profile') {
        expect(in_array('tool_calls_registry', $r['steps'] ?? [], true))->toBeFalse();
    }
});


it("happy: pipeline step agent_tools_profile executes in order [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'] ?? [])->toContain('agent_tools_profile');
});

it("fail: pipeline aborts before tools when step agent_tools_profile fails if prior required [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $failAt = 'agent_tools_profile';
    if ($failAt === 'verify_webhook_secret') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['secret_valid' => false]);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    if ($failAt === 'queue_process_update') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => 'queue_process_update']);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    $map = [
        'resolve_identity' => 'identity_unresolved',
        'map_thread' => 'thread_store_failure',
        'conversation_ingress' => 'ingress_failure',
        'agent_tools_profile' => 'profile_missing',
        'tool_calls_registry' => 'tool_registry_failure',
        'conversation_reply' => 'reply_failure',
    ];
    $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => $map[$failAt] ?? $failAt]);
    expect($r['ok'])->toBeFalse();
    if ($failAt !== 'tool_calls_registry' && $failAt !== 'conversation_reply' && $failAt !== 'agent_tools_profile') {
        expect(in_array('tool_calls_registry', $r['steps'] ?? [], true))->toBeFalse();
    }
});


it("happy: pipeline step tool_calls_registry executes in order [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'] ?? [])->toContain('tool_calls_registry');
});

it("fail: pipeline aborts before tools when step tool_calls_registry fails if prior required [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $failAt = 'tool_calls_registry';
    if ($failAt === 'verify_webhook_secret') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['secret_valid' => false]);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    if ($failAt === 'queue_process_update') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => 'queue_process_update']);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    $map = [
        'resolve_identity' => 'identity_unresolved',
        'map_thread' => 'thread_store_failure',
        'conversation_ingress' => 'ingress_failure',
        'agent_tools_profile' => 'profile_missing',
        'tool_calls_registry' => 'tool_registry_failure',
        'conversation_reply' => 'reply_failure',
    ];
    $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => $map[$failAt] ?? $failAt]);
    expect($r['ok'])->toBeFalse();
    if ($failAt !== 'tool_calls_registry' && $failAt !== 'conversation_reply' && $failAt !== 'agent_tools_profile') {
        expect(in_array('tool_calls_registry', $r['steps'] ?? [], true))->toBeFalse();
    }
});


it("happy: pipeline step conversation_reply executes in order [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    expect($r['steps'] ?? [])->toContain('conversation_reply');
});

it("fail: pipeline aborts before tools when step conversation_reply fails if prior required [MSG-003]", function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $failAt = 'conversation_reply';
    if ($failAt === 'verify_webhook_secret') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['secret_valid' => false]);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    if ($failAt === 'queue_process_update') {
        $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => 'queue_process_update']);
        expect($r['tools_reached'] ?? false)->toBeFalse();
        return;
    }
    $map = [
        'resolve_identity' => 'identity_unresolved',
        'map_thread' => 'thread_store_failure',
        'conversation_ingress' => 'ingress_failure',
        'agent_tools_profile' => 'profile_missing',
        'tool_calls_registry' => 'tool_registry_failure',
        'conversation_reply' => 'reply_failure',
    ];
    $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['fail_at' => $map[$failAt] ?? $failAt]);
    expect($r['ok'])->toBeFalse();
    if ($failAt !== 'tool_calls_registry' && $failAt !== 'conversation_reply' && $failAt !== 'agent_tools_profile') {
        expect(in_array('tool_calls_registry', $r['steps'] ?? [], true))->toBeFalse();
    }
});
