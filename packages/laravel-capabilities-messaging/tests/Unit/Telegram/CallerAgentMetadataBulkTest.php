<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('edge: messaging metadata field channel optional on agent context [D-007]', function () {
    $user = H::linkedUser();
    $ctx = new CapabilityContext(
        caller: 'agent',
        actor: $user,
        messaging: ['channel' => 'sample'],
    );
    expect($ctx->messaging())->toHaveKey('channel');
});

it('fail: messaging metadata field channel not used as authorize authority alone [MSG-002]', function () {
    // Metadata alone does not grant tool access — identity link required
    $identity = H::identity();
    $user = $identity->resolve([
        'telegram_user_id' => 'nope',
        'channel' => 'forged-authority',
    ]);
    expect($user)->toBeNull();
    expect($identity->canUseTools($user))->toBeFalse();
});

it('edge: messaging metadata field chat_id optional on agent context [D-007]', function () {
    $user = H::linkedUser();
    $ctx = new CapabilityContext(
        caller: 'agent',
        actor: $user,
        messaging: ['chat_id' => 'sample'],
    );
    expect($ctx->messaging())->toHaveKey('chat_id');
});

it('fail: messaging metadata field chat_id not used as authorize authority alone [MSG-002]', function () {
    // Metadata alone does not grant tool access — identity link required
    $identity = H::identity();
    $user = $identity->resolve([
        'telegram_user_id' => 'nope',
        'chat_id' => 'forged-authority',
    ]);
    expect($user)->toBeNull();
    expect($identity->canUseTools($user))->toBeFalse();
});

it('edge: messaging metadata field message_id optional on agent context [D-007]', function () {
    $user = H::linkedUser();
    $ctx = new CapabilityContext(
        caller: 'agent',
        actor: $user,
        messaging: ['message_id' => 'sample'],
    );
    expect($ctx->messaging())->toHaveKey('message_id');
});

it('fail: messaging metadata field message_id not used as authorize authority alone [MSG-002]', function () {
    // Metadata alone does not grant tool access — identity link required
    $identity = H::identity();
    $user = $identity->resolve([
        'telegram_user_id' => 'nope',
        'message_id' => 'forged-authority',
    ]);
    expect($user)->toBeNull();
    expect($identity->canUseTools($user))->toBeFalse();
});

it('edge: messaging metadata field topic_id optional on agent context [D-007]', function () {
    $user = H::linkedUser();
    $ctx = new CapabilityContext(
        caller: 'agent',
        actor: $user,
        messaging: ['topic_id' => 'sample'],
    );
    expect($ctx->messaging())->toHaveKey('topic_id');
});

it('fail: messaging metadata field topic_id not used as authorize authority alone [MSG-002]', function () {
    // Metadata alone does not grant tool access — identity link required
    $identity = H::identity();
    $user = $identity->resolve([
        'telegram_user_id' => 'nope',
        'topic_id' => 'forged-authority',
    ]);
    expect($user)->toBeNull();
    expect($identity->canUseTools($user))->toBeFalse();
});

it('edge: messaging metadata field user_link_id optional on agent context [D-007]', function () {
    $user = H::linkedUser();
    $ctx = new CapabilityContext(
        caller: 'agent',
        actor: $user,
        messaging: ['user_link_id' => 'sample'],
    );
    expect($ctx->messaging())->toHaveKey('user_link_id');
});

it('fail: messaging metadata field user_link_id not used as authorize authority alone [MSG-002]', function () {
    // Metadata alone does not grant tool access — identity link required
    $identity = H::identity();
    $user = $identity->resolve([
        'telegram_user_id' => 'nope',
        'user_link_id' => 'forged-authority',
    ]);
    expect($user)->toBeNull();
    expect($identity->canUseTools($user))->toBeFalse();
});
