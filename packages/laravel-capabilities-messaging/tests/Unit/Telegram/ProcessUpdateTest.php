<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('happy: linked identity maps chat to agent turn via ConversationIngress [MSG-003]', function () {
    $identity = H::identity();
    $identity->link('42', 'user-1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->handle(H::telegramUpdate(userId: 42));
    expect($r['ok'])->toBeTrue()->and($r['caller'])->toBe('agent');
});

it('happy: agent tools use configured profile not full catalog [D-008]', function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity, 'profile_tools' => ['support.ping']]);
    $r = $p->handle(H::telegramUpdate(userId: 42));
    expect($r['profile'])->toBe('support')->and($r['tools'])->toBe(['support.ping']);
});

it('happy: tool calls go through CapabilityRegistry only [D-007]', function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $registry = new FakeCapabilityBus;
    $adapter = new TelegramAdapter(H::bot(), static fn (array $m) => [
        'text' => 'ok',
        'tool_calls' => [['name' => 'support.ping', 'input' => []]],
    ]);
    $p = H::processor([
        'identity' => $identity,
        'registry' => $registry,
        'adapter' => $adapter,
        'profile_tools' => ['support.ping'],
    ]);
    $r = $p->handle(H::telegramUpdate(userId: 42));
    expect($r['ok'])->toBeTrue()->and($registry->invokeCount())->toBe(1);
});

it('happy: ConversationReply sends response via Bot API mock [MSG-003]', function () {
    $bot = H::bot();
    $identity = H::identity();
    $identity->link('42', 'u1');
    $adapter = new TelegramAdapter($bot);
    $p = H::processor(['identity' => $identity, 'adapter' => $adapter, 'bot' => $bot]);
    $p->handle(H::telegramUpdate(userId: 42, chatId: 77));
    expect($bot->calls())->not->toBeEmpty();
    expect($bot->calls()[0]['method'])->toBe('sendMessage');
});

it('fail: unlinked Telegram user never gets tool access [MSG-002]', function () {
    $registry = new FakeCapabilityBus;
    $p = H::processor(['registry' => $registry]);
    $r = $p->handle(H::telegramUpdate(userId: 999));
    expect($r['ok'])->toBeFalse()->and($registry->invokeCount())->toBe(0);
});

it('fail: adapter never calls Eloquent domain services directly [D-007]', function () {
    $adapter = new TelegramAdapter;
    expect($adapter->ownsDomainRunPath())->toBeFalse();
    $hits = H::scanSource('/use\s+App\\\\Models\\\\/');
    expect($hits)->toBeEmpty();
});

it('fail: adapter never owns second run path [D-007]', function () {
    expect(method_exists(TelegramAdapter::class, 'run'))->toBeFalse();
    expect((new TelegramAdapter)->ownsDomainRunPath())->toBeFalse();
});

it('happy: thread store maps chat topic to conversation thread [MSG-004]', function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $threads = H::threads();
    $p = H::processor(['identity' => $identity, 'threads' => $threads]);
    $r = $p->handle(H::telegramUpdate(userId: 42, topicId: 3));
    expect($r['thread_id'])->toBe($threads->threadIdFor('100', 3));
});

it('edge: failed ProcessTelegramUpdate tags channel for failed jobs [D-019]', function () {
    $p = H::processor();
    $tags = $p->failedJobTags(H::telegramUpdate(chatId: 5, updateId: 9));
    expect($tags['channel'])->toBe('telegram')
        ->and($tags['chat_id'])->toBe('5')
        ->and($tags['update_id'])->toBe(9);
});

it('fail: unlinked allowlist miss never starts agent turn with tools [MSG-002]', function () {
    $identity = H::identity([
        'identity' => [
            'mode' => 'allowlist',
            'allowlist' => [
                ['telegram_user_id' => 'allowed', 'laravel_user_id' => 'u1'],
            ],
        ],
    ]);
    $registry = new FakeCapabilityBus;
    $p = H::processor(['identity' => $identity, 'registry' => $registry]);
    $r = $p->handle(H::telegramUpdate(userId: 'not-allowed'));
    expect($r['ok'])->toBeFalse()->and($registry->invokeCount())->toBe(0);
});

it('happy: agent profile from messaging config not full catalog [D-008]', function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $cfg = H::config(['agent_profile' => 'support']);
    $p = H::processor(['config' => $cfg, 'identity' => $identity, 'profile_tools' => ['a']]);
    $r = $p->handle(H::telegramUpdate(userId: 42));
    expect($r['profile'])->toBe('support');
});

it('edge: queue ProcessTelegramUpdate async not sync domain mutation [MSG-003]', function () {
    $q = H::queue();
    $ctrl = H::webhook([], $q);
    $ctrl->handle(['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'], H::telegramUpdate());
    expect($q->count())->toBe(1)->and($ctrl->registryInvokeCount())->toBe(0);
});

it('happy: pipeline verify secret then queue then identity then thread then ingress [MSG-003]', function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42), ['secret_valid' => true]);
    expect($r['ok'])->toBeTrue();
    expect($r['steps'][0])->toBe('verify_webhook_secret');
    expect($r['steps'][1])->toBe('queue_process_update');
    expect($r['steps'])->toContain('resolve_identity')
        ->and($r['steps'])->toContain('map_thread')
        ->and($r['steps'])->toContain('conversation_ingress');
});
